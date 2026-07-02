<?php

namespace App\Models;

use App\Enums\InvoiceStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Facture rattachée à une ligne financière (P1-16-3). Cycle de vie :
 * Brouillon → Émise (numérotée) → Payée / Annulée. Une facture ne peut être
 * émise sans montant ni destinataire (P1-16-4).
 */
class Invoice extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'importee' => 'boolean',
            'statut' => InvoiceStatut::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'statut', 'destinataire', 'montant', 'date_emission', 'date_echeance'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('facture');
    }

    public function financeLine(): BelongsTo
    {
        return $this->belongsTo(FinanceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class)->latest('date_paiement');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function montantPaye(): float
    {
        return (float) $this->payments()->sum('montant');
    }

    public function resteAPayer(): float
    {
        return max(0, round((float) $this->montant - $this->montantPaye(), 2));
    }

    /** Émise, échue et non intégralement payée. */
    public function estEnRetard(): bool
    {
        return $this->statut === InvoiceStatut::Emise
            && $this->date_echeance !== null
            && $this->date_echeance->isPast()
            && $this->resteAPayer() > 0;
    }

    /**
     * P1-16-4 : refuse l'émission d'une facture sans montant ni destinataire.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === InvoiceStatut::Emise) {
            if ((float) $this->montant <= 0) {
                return "Émission impossible : la facture doit porter un montant supérieur à zéro.";
            }

            if (blank($this->destinataire)) {
                return 'Émission impossible : un destinataire est obligatoire.';
            }
        }

        return null;
    }

    /** Effets de bord : numérotation et date d'émission au passage à « Émise ». */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === InvoiceStatut::Emise) {
            $this->forceFill([
                'numero' => $this->numero ?: $this->genererNumero(),
                'date_emission' => $this->date_emission ?? now()->toDateString(),
            ])->saveQuietly();
        }
    }

    /** Numéro séquentiel « FACT-AAAA-000N » (par année d'émission). */
    protected function genererNumero(): string
    {
        $annee = now()->format('Y');
        $rang = static::withTrashed()
            ->whereYear('date_emission', $annee)
            ->whereNotNull('numero')
            ->count() + 1;

        return sprintf('FACT-%s-%04d', $annee, $rang);
    }
}
