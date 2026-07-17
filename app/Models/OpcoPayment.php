<?php

namespace App\Models;

use App\Enums\PaymentStatut;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Traduction SQL de estEnRetard() — source unique du « en retard ».
     *
     * Ne PAS filtrer sur le statut « En retard » : la commande quotidienne
     * opco:flag-echeances bascule justement « Attendu » → « En retard ». Un
     * compteur qui ne cherchait que les « Attendu » échus perdait la ligne au
     * moment précis où le système reconnaissait le retard — l'alerte s'éteignait
     * après moins de 24 h, sur un versement toujours impayé.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEnRetard(Builder $query): Builder
    {
        return $query
            ->where('statut', '!=', PaymentStatut::Verse->value)
            ->whereNotNull('date_prevue')
            ->whereDate('date_prevue', '<', now()->toDateString());
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
