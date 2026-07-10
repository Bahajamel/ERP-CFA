<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Parcours\CycleApprenant;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Matching extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
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

    /**
     * CV joint à la proposition envoyée à l'entreprise (traçabilité de la
     * pièce réellement transmise : CV existant du candidat recopié, ou CV
     * ajouté au moment de l'envoi vers Matching).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }

    /** Statut terminal de refus (motif obligatoire à la transition). */
    private const REFUS = [MatchingStatut::Refuse];

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
     * Règles système appliquées à l'enregistrement, quel que soit le chemin
     * (formulaire, transition d'état, écriture directe) :
     *  - cycle apprenant : seul un candidat **accepté par le CFA** entre au
     *    Matching (un candidat refusé ne poursuit jamais le cycle) ;
     *  - P0-05-5 : pas d'« Accepté » sur un besoin clôturé ;
     *  - Proposition envoyée exige que le CV soit marqué envoyé ;
     *  - Entretien entreprise exige une date d'entretien ;
     *  - Refusé exige un motif de refus ou un commentaire (retour entreprise).
     *
     * Les trois dernières encadrent une **transition** (mise à jour d'un matching
     * existant) : la saisie d'un historique à la création (seeds/imports) reste libre.
     */
    protected static function booted(): void
    {
        static::creating(function (self $matching): void {
            $candidate = Candidate::query()->find($matching->candidate_id);

            if ($candidate?->statut !== CandidateStatut::Accepte) {
                throw ValidationException::withMessages([
                    'candidate_id' => CycleApprenant::MSG_CANDIDAT_NON_ACCEPTE,
                ]);
            }
        });

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

            // Au-delà de « En recherche », une entreprise (besoin) doit être rattachée.
            if ($matching->need_id === null && in_array($statut, [
                MatchingStatut::PropositionEnvoyee,
                MatchingStatut::EntretienEntreprise,
                MatchingStatut::Accepte,
            ], true)) {
                throw ValidationException::withMessages([
                    'need_id' => 'Rattachez une entreprise (besoin) avant de faire avancer ce matching.',
                ]);
            }

            if ($statut === MatchingStatut::PropositionEnvoyee && ! $matching->cv_envoye) {
                throw ValidationException::withMessages([
                    'cv_envoye' => 'Proposition envoyée : marquez le CV comme envoyé avant de passer à ce statut.',
                ]);
            }

            if ($statut === MatchingStatut::EntretienEntreprise && blank($matching->date_entretien)) {
                throw ValidationException::withMessages([
                    'date_entretien' => 'Entretien entreprise : renseignez la date d\'entretien.',
                ]);
            }

            if (in_array($statut, self::REFUS, true)
                && blank($matching->refusal_reason) && blank($matching->retour_entreprise)) {
                throw ValidationException::withMessages([
                    'refusal_reason' => 'Refus : indiquez un motif de refus ou un commentaire (retour entreprise).',
                ]);
            }
        });

        // Déclencheur automatique du cycle : matching accepté → contrat créé
        // (ou rouvert) dans la section Contrats, prérempli depuis le besoin.
        static::updated(function (self $matching): void {
            if (! $matching->wasChanged('statut') || $matching->statut !== MatchingStatut::Accepte) {
                return;
            }

            try {
                $contract = app(CycleApprenant::class)->creerContratDepuisMatching($matching);
            } catch (\App\Parcours\CycleBloqueException) {
                return; // Sans entreprise rattachée, rien à créer (déjà bloqué en amont).
            }

            if ($contract->wasRecentlyCreated) {
                CycleApprenant::notifierAutomatisme(
                    'Matching accepté',
                    'Contrat créé automatiquement pour '.($matching->candidate?->nom_complet ?? 'le candidat')
                    .' — complétez les dates puis lancez la signature des trois parties.',
                );
            }
        });
    }

    /**
     * Gardes de transition (machine à états) : masquent la cible et affichent un
     * message explicite. Miroir des invariants ci-dessus pour le chemin transitionTo().
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($this->need_id === null && in_array($to, [
            MatchingStatut::PropositionEnvoyee,
            MatchingStatut::EntretienEntreprise,
            MatchingStatut::Accepte,
        ], true)) {
            return 'Rattachez une entreprise (besoin) avant de faire avancer ce matching.';
        }

        if ($to === MatchingStatut::Accepte && $this->besoinEstCloture()) {
            return 'Besoin clôturé : impossible d\'accepter ce candidat.';
        }

        if ($to === MatchingStatut::PropositionEnvoyee && ! $this->cv_envoye) {
            return 'Marquez le CV comme envoyé avant de passer à ce statut.';
        }

        if ($to === MatchingStatut::EntretienEntreprise && blank($this->date_entretien)) {
            return 'Renseignez la date d\'entretien avant de passer à ce statut.';
        }

        if (in_array($to, self::REFUS, true)
            && blank($this->refusal_reason) && blank($this->retour_entreprise)) {
            return 'Indiquez un motif de refus ou un commentaire avant de refuser.';
        }

        return null;
    }

    /** Le matching provient-il d'une entreprise trouvée par le candidat ? */
    public function estOrigineCandidat(): bool
    {
        return $this->origine === CycleApprenant::ORIGINE_CANDIDAT;
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
