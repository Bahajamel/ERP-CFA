<?php

namespace App\Models;

use App\Enums\MatchingStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Matching extends Model
{
    use HasFactory;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cv_envoye' => 'boolean',
            'date_entretien' => 'date',
            'statut' => MatchingStatut::class,
        ];
    }

    /**
     * Règle système (P0-05-5) : on ne peut pas accepter un candidat sur un besoin
     * déjà clôturé. Enforcé à l'enregistrement, quel que soit le chemin (formulaire,
     * transition d'état, écriture directe).
     */
    protected static function booted(): void
    {
        static::saving(function (self $matching): void {
            if ($matching->statut === MatchingStatut::Accepte
                && $matching->isDirty('statut')
                && $matching->besoinEstCloture()) {
                throw ValidationException::withMessages([
                    'statut' => 'Besoin clôturé : impossible d\'accepter un candidat sur ce besoin.',
                ]);
            }
        });
    }

    /**
     * Garde de transition (P0-05-5) : bloque « Accepté » si le besoin est clôturé.
     * Utilisée par la machine à états (masque la cible, message explicite).
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === MatchingStatut::Accepte && $this->besoinEstCloture()) {
            return 'Besoin clôturé : impossible d\'accepter ce candidat.';
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
