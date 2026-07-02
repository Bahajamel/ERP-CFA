<?php

namespace App\Models;

use App\Enums\InvoiceStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ligne financière d'un contrat (P1-16-1). Porte les montants attendu / accepté /
 * bloqué ; le facturé et l'encaissé sont dérivés des factures et paiements liés.
 */
class FinanceLine extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'montant_attendu' => 'decimal:2',
            'montant_accepte' => 'decimal:2',
            'montant_bloque' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // P1-16-4 : un montant bloqué exige un motif.
        static::saving(function (FinanceLine $line): void {
            if ((float) $line->montant_bloque > 0 && blank($line->motif_blocage)) {
                throw ValidationException::withMessages([
                    'motif_blocage' => 'Un montant bloqué exige un motif de blocage.',
                ]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['libelle', 'montant_attendu', 'montant_accepte', 'montant_bloque', 'motif_blocage'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('finance');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class)->latest('date_paiement');
    }

    /** Base facturable : le montant accepté s'il est renseigné, sinon l'attendu. */
    public function montantFacturable(): float
    {
        $accepte = (float) $this->montant_accepte;

        return $accepte > 0 ? $accepte : (float) $this->montant_attendu;
    }

    /** Total des factures émises (émises ou payées). */
    public function montantFacture(): float
    {
        return (float) $this->invoices()
            ->whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])
            ->sum('montant');
    }

    /** Total encaissé (somme des paiements de la ligne). */
    public function montantEncaisse(): float
    {
        return (float) $this->payments()->sum('montant');
    }

    public function resteAFacturer(): float
    {
        return max(0, round($this->montantFacturable() - $this->montantFacture(), 2));
    }

    public function resteAEncaisser(): float
    {
        return max(0, round($this->montantFacture() - $this->montantEncaisse(), 2));
    }

    public function estBloque(): bool
    {
        return (float) $this->montant_bloque > 0;
    }
}
