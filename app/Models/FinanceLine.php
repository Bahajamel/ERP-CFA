<?php

namespace App\Models;

use App\Enums\FinanceLineStatut;
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

    /** Dossier OPCO à l'origine de la ligne (génération automatique). */
    public function opcoFile(): BelongsTo
    {
        return $this->belongsTo(OpcoFile::class);
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

    /** Une des factures de la ligne est-elle échue et impayée ? */
    public function aFactureEnRetard(): bool
    {
        return $this->invoices->contains(fn (Invoice $invoice) => $invoice->estEnRetard());
    }

    /**
     * Statut « santé » calculé de la ligne (jamais stocké) — voir
     * {@see FinanceLineStatut} pour l'ordre de priorité.
     */
    public function statut(): FinanceLineStatut
    {
        if ($this->estBloque()) {
            return FinanceLineStatut::Bloquee;
        }

        if ($this->aFactureEnRetard()) {
            return FinanceLineStatut::EnRetard;
        }

        $facturable = $this->montantFacturable();
        $facture = $this->montantFacture();
        $encaisse = $this->montantEncaisse();

        if ($facturable > 0 && $encaisse >= round($facturable, 2)) {
            return FinanceLineStatut::Soldee;
        }

        if ($facture <= 0) {
            return FinanceLineStatut::ABacturer;
        }

        if ($encaisse > 0 && $encaisse < $facture) {
            return FinanceLineStatut::EncaissementEnCours;
        }

        if ($facture < round($facturable, 2)) {
            return FinanceLineStatut::PartiellementFacturee;
        }

        return FinanceLineStatut::EncaissementEnCours;
    }
}
