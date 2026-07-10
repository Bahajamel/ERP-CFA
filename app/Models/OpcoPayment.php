<?php

namespace App\Models;

use App\Enums\PaymentStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcoPayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pourcentage' => 'decimal:2',
            'montant_prevu' => 'decimal:2',
            'montant_verse' => 'decimal:2',
            'date_prevue' => 'date',
            'date_versement' => 'date',
            'statut' => PaymentStatut::class,
        ];
    }

    public function opcoFile(): BelongsTo
    {
        return $this->belongsTo(OpcoFile::class);
    }

    /** En retard si non versé et date d'échéance dépassée. */
    public function estEnRetard(): bool
    {
        return $this->statut !== PaymentStatut::Verse
            && $this->date_prevue !== null
            && $this->date_prevue->isPast();
    }

    /** Marque le versement comme reçu. */
    public function marquerVerse(?float $montant = null, ?\DateTimeInterface $date = null): void
    {
        $this->forceFill([
            'statut' => PaymentStatut::Verse,
            'montant_verse' => $montant ?? $this->montant_prevu,
            'date_versement' => $date ?? now(),
        ])->save();
    }
}
