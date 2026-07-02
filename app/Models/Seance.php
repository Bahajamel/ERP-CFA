<?php

namespace App\Models;

use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Séance de formation (EPIC-14) : créneau daté d'une promotion, support de
 * l'émargement. À la création, une ligne de présence « non renseigné » est
 * générée pour chaque apprenti de la promotion.
 */
class Seance extends Model
{
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
        // sont renseignées (base du service fait).
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
}
