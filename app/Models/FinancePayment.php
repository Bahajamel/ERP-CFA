<?php

namespace App\Models;

use App\Enums\InvoiceStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Encaissement (P1-16-2). Toujours relié à une ligne financière et,
 * facultativement, à une facture précise (P1-16-4). Lorsqu'une facture est
 * intégralement encaissée, elle bascule automatiquement à « Payée ».
 */
class FinancePayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // P1-16-4 : un paiement doit être relié à une facture ou une ligne.
        static::saving(function (FinancePayment $payment): void {
            if (blank($payment->finance_line_id) && blank($payment->invoice_id)) {
                throw ValidationException::withMessages([
                    'finance_line_id' => 'Un paiement doit être relié à une facture ou une ligne financière.',
                ]);
            }

            // Cohérence : rattache la ligne depuis la facture si absente.
            if (blank($payment->finance_line_id) && $payment->invoice) {
                $payment->finance_line_id = $payment->invoice->finance_line_id;
            }
        });

        // Auto-clôture : facture soldée → « Payée ».
        static::saved(function (FinancePayment $payment): void {
            $invoice = $payment->invoice;

            if ($invoice
                && $invoice->statut === InvoiceStatut::Emise
                && $invoice->resteAPayer() <= 0) {
                $invoice->transitionTo(InvoiceStatut::Payee);
            }
        });
    }

    public function financeLine(): BelongsTo
    {
        return $this->belongsTo(FinanceLine::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
