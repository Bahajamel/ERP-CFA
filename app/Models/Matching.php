<?php

namespace App\Models;

use App\Enums\MatchingStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Matching extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cv_envoye' => 'boolean',
            'date_entretien' => 'date',
            'next_action_at' => 'date',
            'statut' => MatchingStatut::class,
        ];
    }

    /** Statuts terminaux de refus (motif obligatoire à la transition). */
    private const REFUS = [MatchingStatut::RefuseEntreprise, MatchingStatut::RefuseCandidat];

    /**
     * Journalise les évolutions du matching (activity log) — traçabilité commerciale
     * et audit. Seuls les champs métier significatifs sont suivis.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'cv_envoye', 'date_entretien', 'retour_entreprise', 'refusal_reason', 'next_action_at', 'assigned_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('matching');
    }

    /**
     * Règles système (EPIC-05) appliquées à l'enregistrement, quel que soit le
     * chemin (formulaire, transition d'état, écriture directe) :
     *  - P0-05-5 : pas d'« Accepté » sur un besoin clôturé ;
     *  - CV envoyé exige que le CV soit marqué envoyé ;
     *  - Entretien prévu exige une date d'entretien ;
     *  - Refusé exige un motif de refus ou un commentaire (retour entreprise).
     *
     * Les trois dernières encadrent une **transition** (mise à jour d'un matching
     * existant) : la saisie d'un historique à la création (seeds/imports) reste libre.
     */
    protected static function booted(): void
    {
        static::saving(function (self $matching): void {
            if (! $matching->isDirty('statut')) {
                return;
            }

            $statut = $matching->statut;

            if ($statut === MatchingStatut::Accepte && $matching->besoinEstCloture()) {
                throw ValidationException::withMessages([
                    'statut' => 'Besoin clôturé : impossible d\'accepter un candidat sur ce besoin.',
                ]);
            }

            // Gardes de transition : n'entravent pas la création d'un état historique.
            if (! $matching->exists) {
                return;
            }

            if ($statut === MatchingStatut::CvEnvoye && ! $matching->cv_envoye) {
                throw ValidationException::withMessages([
                    'cv_envoye' => 'CV envoyé : marquez le CV comme envoyé avant de passer à ce statut.',
                ]);
            }

            if ($statut === MatchingStatut::EntretienPrevu && blank($matching->date_entretien)) {
                throw ValidationException::withMessages([
                    'date_entretien' => 'Entretien prévu : renseignez la date d\'entretien.',
                ]);
            }

            if (in_array($statut, self::REFUS, true)
                && blank($matching->refusal_reason) && blank($matching->retour_entreprise)) {
                throw ValidationException::withMessages([
                    'refusal_reason' => 'Refus : indiquez un motif de refus ou un commentaire (retour entreprise).',
                ]);
            }
        });
    }

    /**
     * Gardes de transition (machine à états) : masquent la cible et affichent un
     * message explicite. Miroir des invariants ci-dessus pour le chemin transitionTo().
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === MatchingStatut::Accepte && $this->besoinEstCloture()) {
            return 'Besoin clôturé : impossible d\'accepter ce candidat.';
        }

        if ($to === MatchingStatut::CvEnvoye && ! $this->cv_envoye) {
            return 'Marquez le CV comme envoyé avant de passer à ce statut.';
        }

        if ($to === MatchingStatut::EntretienPrevu && blank($this->date_entretien)) {
            return 'Renseignez la date d\'entretien avant de passer à ce statut.';
        }

        if (in_array($to, self::REFUS, true)
            && blank($this->refusal_reason) && blank($this->retour_entreprise)) {
            return 'Indiquez un motif de refus ou un commentaire avant de refuser.';
        }

        return null;
    }

    /** Le besoin rattaché est-il clôturé (statut terminal) ? */
    public function besoinEstCloture(): bool
    {
        return $this->need?->estCloture() ?? false;
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
