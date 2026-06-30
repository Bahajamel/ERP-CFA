<?php

namespace App\Models;

use App\Enums\MatchingStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matching extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cv_envoye' => 'boolean',
            'date_entretien' => 'date',
            'statut' => MatchingStatut::class,
        ];
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
