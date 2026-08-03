<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * Séance de formation (EPIC-14) : créneau daté d'une promotion, support de
 * l'émargement. À la création, une ligne de présence « non renseigné » est
 * générée pour chaque apprenti de la promotion.
 */
class Seance extends Model
{
    use BelongsToOrganisation;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'statut' => SeanceStatut::class,
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $seance) => $seance->genererPresences());

        // Règle (P1-14-5) : une séance n'est validable que si toutes les présences
        // sont renseignées.
        static::saving(function (self $seance): void {
            if ($seance->statut === SeanceStatut::Validee
                && $seance->isDirty('statut')
                && $seance->exists
                && $seance->aDesPresencesNonRenseignees()) {
                throw ValidationException::withMessages([
                    'statut' => 'Séance non validable : des présences ne sont pas renseignées.',
                ]);
            }
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function formateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formateur_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /** Documents rattachés à la séance (GED) — feuilles d'émargement scannées. */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Dernière feuille d'émargement scannée (version courante), s'il y en a une. */
    public function feuilleEmargement(): ?Document
    {
        return $this->documents()
            ->where('type', DocumentType::FeuilleEmargement)
            ->latest('version')
            ->latest('id')
            ->first();
    }

    /** Génère une présence « non renseigné » par apprenti de la promotion. */
    public function genererPresences(): void
    {
        $this->promotion?->apprentis()->pluck('candidates.id')->each(
            fn ($candidateId) => $this->presences()->firstOrCreate(
                ['candidate_id' => $candidateId],
                ['statut' => PresenceStatut::NonRenseigne->value],
            ),
        );
    }

    /**
     * Compose la liste d'émargement de la séance : présences conservées/créées
     * pour les apprenants cochés, retirées pour les autres — au sein d'une même
     * cohorte, les options (matières) suivies peuvent différer.
     */
    public function composerParticipants(array $candidateIds): void
    {
        if ($candidateIds === []) {
            return; // rien de coché → liste générée par défaut (toute la cohorte)
        }

        $this->presences()->whereNotIn('candidate_id', $candidateIds)->delete();

        collect($candidateIds)->each(fn ($id) => $this->presences()->firstOrCreate(
            ['candidate_id' => $id],
            ['statut' => PresenceStatut::NonRenseigne->value],
        ));
    }

    /** Reste-t-il des présences non renseignées ? (garde de validation, P1-14-5) */
    public function aDesPresencesNonRenseignees(): bool
    {
        return $this->presences()->where('statut', PresenceStatut::NonRenseigne->value)->exists();
    }

    /** Taux de présence de la séance (présents / total renseigné), en %. */
    public function tauxPresence(): ?int
    {
        $total = $this->presences()->where('statut', '!=', PresenceStatut::NonRenseigne->value)->count();

        if ($total === 0) {
            return null;
        }

        $presents = $this->presences()
            ->whereIn('statut', array_map(fn (PresenceStatut $s) => $s->value, PresenceStatut::presents()))
            ->count();

        return (int) round($presents / $total * 100);
    }

    /** Toutes les présences sont-elles renseignées (séance prête à valider) ? */
    public function estComplete(): bool
    {
        return $this->presences()->exists() && ! $this->aDesPresencesNonRenseignees();
    }

    /**
     * Statut d'affichage (dérivé) pour l'UI : au-delà des 3 statuts stockés
     * (planifiée/validée/annulée), on distingue « En cours » (aujourd'hui),
     * « À compléter » (présences manquantes) et « À valider » (complète). Ne
     * modifie PAS l'enum SeanceStatut.
     *
     * @return array{label: string, color: string}
     */
    public function statutAffiche(): array
    {
        return match ($this->statut) {
            SeanceStatut::Annulee => ['label' => 'Annulée', 'color' => 'danger'],
            SeanceStatut::Validee => ['label' => 'Validée', 'color' => 'success'],
            default => match (true) {
                $this->date->isFuture() => ['label' => 'Planifiée', 'color' => 'gray'],
                $this->date->isToday() => ['label' => 'En cours', 'color' => 'info'],
                $this->aDesPresencesNonRenseignees() => ['label' => 'À compléter', 'color' => 'warning'],
                default => ['label' => 'À valider', 'color' => 'info'],
            },
        };
    }

    /**
     * État de l'émargement (feuille / signatures) pour l'UI.
     *
     * @return array{label: string, color: string}
     */
    public function etatEmargement(): array
    {
        if ($this->feuilleEmargement() !== null) {
            return ['label' => 'Scan déposé', 'color' => 'success'];
        }

        $total = $this->presences()->count();
        $signes = $this->presences()->whereNotNull('signed_at')->count();

        if ($signes > 0) {
            return ['label' => "Signé {$signes}/{$total}", 'color' => $signes >= $total && $total > 0 ? 'success' : 'info'];
        }

        if ($this->presences()->whereNotNull('signature_token')->exists()) {
            return ['label' => 'Signatures ouvertes', 'color' => 'info'];
        }

        if ($this->date->isPast() && ! $this->date->isToday()) {
            return ['label' => 'Aucune feuille', 'color' => 'warning'];
        }

        return ['label' => 'À ouvrir', 'color' => 'gray'];
    }
}
