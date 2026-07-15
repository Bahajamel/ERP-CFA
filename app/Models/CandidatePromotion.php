<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot apprenant ↔ cohorte, enrichi de l'inscription à la carte : matières
 * choisies au sein du programme, jeton d'invitation et horodatages
 * (invitation envoyée / réponse de l'apprenant).
 */
class CandidatePromotion extends Pivot
{
    protected $table = 'candidate_promotion';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'matieres' => 'array',
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function candidate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** L'apprenant a-t-il déjà répondu au formulaire de choix des matières ? */
    public function aRepondu(): bool
    {
        return $this->responded_at !== null;
    }
}
