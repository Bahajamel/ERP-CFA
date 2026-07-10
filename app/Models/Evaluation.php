<?php

namespace App\Models;

use App\Enums\EvaluationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'un apprenant pour une matière, une épreuve et une classe. Ramenée sur
 * 20 (`noteSur20`) pour les moyennes des bulletins, pondérée par coefficient.
 */
class Evaluation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => EvaluationType::class,
            'note' => 'decimal:2',
            'bareme' => 'decimal:2',
            'coefficient' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** La note ramenée sur 20 (selon le barème de l'épreuve). */
    public function noteSur20(): float
    {
        $bareme = (float) $this->bareme;

        if ($bareme <= 0) {
            return (float) $this->note;
        }

        return round((float) $this->note / $bareme * 20, 2);
    }
}
