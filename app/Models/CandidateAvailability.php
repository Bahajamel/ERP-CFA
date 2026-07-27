<?php

namespace App\Models;

use App\Enums\AvailabilityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Période de disponibilité (ou d'indisponibilité) d'un candidat. Structure
 * évolutive : un candidat peut en porter plusieurs.
 */
class CandidateAvailability extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AvailabilityType::class,
            'immediate' => 'boolean',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** Libellé lisible d'une période, pour l'affichage en fiche. */
    public function libelle(): string
    {
        if ($this->immediate) {
            return $this->type->getLabel().' — immédiatement';
        }

        $debut = $this->date_debut?->format('d/m/Y');
        $fin = $this->date_fin?->format('d/m/Y');

        return match (true) {
            $debut && $fin => "{$this->type->getLabel()} du {$debut} au {$fin}",
            (bool) $debut => "{$this->type->getLabel()} à partir du {$debut}",
            (bool) $fin => "{$this->type->getLabel()} jusqu'au {$fin}",
            default => $this->type->getLabel(),
        };
    }
}
