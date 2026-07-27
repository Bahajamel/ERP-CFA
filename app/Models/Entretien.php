<?php

namespace App\Models;

use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Models\Concerns\BelongsToOrganisation;
use App\Parcours\CycleApprenant;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Entretien candidat (section Entretiens) : créneau, mode, compte-rendu et
 * décision. Le statut du candidat lié est synchronisé automatiquement :
 * entretien planifié → « Entretien prévu » ; annulé / absent / à
 * reprogrammer → retour à « Entretien à planifier » (sauf décision finale).
 */
class Entretien extends Model
{
    use BelongsToOrganisation;
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $table = 'entretiens';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_entretien' => 'date',
            'mode' => EntretienMode::class,
            'statut' => EntretienStatut::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'date_entretien', 'heure_debut', 'heure_fin', 'mode', 'resultat', 'responsable_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('entretien');
    }

    /** Le créneau est-il complet (date + heure de début + heure de fin) ? */
    public function creneauComplet(): bool
    {
        return filled($this->date_entretien) && filled($this->heure_debut) && filled($this->heure_fin);
    }

    /**
     * Invariants backend, quel que soit le chemin d'écriture :
     *  - « Planifié » exige un créneau complet (date + heures) ;
     *  - l'heure de fin doit suivre l'heure de début.
     *
     * La synchronisation du statut candidat se fait après enregistrement
     * (`saved`) : le service central applique les règles du cycle.
     */
    /** Statuts d'un entretien « en cours » (un seul actif par candidat). */
    public const ACTIFS = [
        EntretienStatut::APlanifier,
        EntretienStatut::Planifie,
        EntretienStatut::AReprogrammer,
        EntretienStatut::Absent,
    ];

    protected static function booted(): void
    {
        // Anti-doublon : un candidat ne peut avoir qu'un seul entretien actif à
        // la fois. On reprogramme l'entretien existant plutôt que d'en créer un
        // second (relation intelligente Candidats ↔ Entretiens). Invariant
        // backend, quel que soit le chemin d'écriture.
        static::creating(function (self $entretien): void {
            $dejaActif = static::query()
                ->where('candidate_id', $entretien->candidate_id)
                ->whereIn('statut', array_map(fn (EntretienStatut $s) => $s->value, self::ACTIFS))
                ->exists();

            if ($dejaActif) {
                throw ValidationException::withMessages([
                    'candidate_id' => CycleApprenant::MSG_ENTRETIEN_EN_COURS,
                ]);
            }
        });

        static::saving(function (self $entretien): void {
            if ($entretien->statut === EntretienStatut::Planifie && ! $entretien->creneauComplet()) {
                throw ValidationException::withMessages([
                    'statut' => CycleApprenant::MSG_ENTRETIEN_INCOMPLET,
                ]);
            }

            if (filled($entretien->heure_debut) && filled($entretien->heure_fin)
                && $entretien->heure_fin <= $entretien->heure_debut) {
                throw ValidationException::withMessages([
                    'heure_fin' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
                ]);
            }
        });

        static::saved(function (self $entretien): void {
            if ($entretien->wasRecentlyCreated || $entretien->wasChanged('statut')) {
                app(CycleApprenant::class)->synchroniserCandidatDepuisEntretien($entretien);
            }
        });
    }

    /** Garde de la machine à états : « Planifié » exige un créneau complet. */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === EntretienStatut::Planifie && ! $this->creneauComplet()) {
            return CycleApprenant::MSG_ENTRETIEN_INCOMPLET;
        }

        return null;
    }

    /** Créneau lisible (fiche, calendrier) : « lun. 12/01 · 09:00 – 10:00 ». */
    public function creneauLisible(): string
    {
        if (! $this->creneauComplet()) {
            return 'Créneau à définir';
        }

        return $this->date_entretien->translatedFormat('D d/m/Y')
            .' · '.substr((string) $this->heure_debut, 0, 5)
            .' – '.substr((string) $this->heure_fin, 0, 5);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
