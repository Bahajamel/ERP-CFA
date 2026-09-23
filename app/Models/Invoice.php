<?php

namespace App\Models;

use App\Enums\InvoiceStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Concerns\BelongsToOrganisation;
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
 * Brouillon (proforma interne) → Émise → Payée / Annulée.
 *
 * L'ERP n'est PAS le système de facturation : la pièce fiscale est produite par
 * la comptabilité (numéro légal saisi ou PDF importé). Le PDF généré ici est un
 * proforma sans valeur comptable. Une facture ne peut être émise sans montant
 * ni destinataire (P1-16-4).
 */
class Invoice extends Model
{
    use BelongsToOrganisation;
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

    /** Échéance de versement OPCO (décret 2025-585) couverte par cette facture. */
    public function opcoPayment(): BelongsTo
    {
        return $this->belongsTo(OpcoPayment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class)->latest('date_paiement');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /** Clé de la tâche de relance impayé (alerte auto). */
    public function cleRelance(): string
    {
        return "finance:impaye:{$this->id}";
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

    /** Nombre de jours de retard (0 si la facture n'est pas en retard). */
    public function joursDeRetard(): int
    {
        if (! $this->estEnRetard()) {
            return 0;
        }

        return (int) $this->date_echeance->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** Priorité de la relance, escaladée selon l'ancienneté du retard. */
    public function prioriteRelance(): TaskPriorite
    {
        return match (true) {
            $this->joursDeRetard() >= 30 => TaskPriorite::Urgente,
            $this->joursDeRetard() >= 15 => TaskPriorite::Haute,
            default => TaskPriorite::Normale,
        };
    }

    /**
     * Ouvre (ou met à jour) la tâche de relance d'impayé rattachée à cette
     * facture. Idempotent : une seule tâche par facture (clé {@see cleRelance()}),
     * ré-exécutable sans doublon — la priorité et l'échéance sont réactualisées à
     * chaque passage. Retourne null si la facture n'est pas en retard.
     *
     * Le CFA (organisation_id) est repris de la facture : indispensable en
     * contexte commande planifiée, où aucun tenant courant n'est défini.
     */
    public function ouvrirRelance(?int $userId = null): ?Task
    {
        if (! $this->estEnRetard()) {
            return null;
        }

        $jours = $this->joursDeRetard();
        $reste = number_format($this->resteAPayer(), 2, ',', ' ');
        $apprenti = $this->financeLine?->contract?->candidate?->nom_complet;

        return $this->tasks()->updateOrCreate(
            ['cle' => $this->cleRelance()],
            [
                'titre' => 'Relancer l\'impayé — facture '.($this->numero ?? '#'.$this->id),
                'description' => 'Facture'.($apprenti ? ' de '.$apprenti : '')
                    .' échue depuis '.$jours.' jour(s) ('.$this->date_echeance->format('d/m/Y').')'
                    .' — reste à payer '.$reste.' €'
                    .($this->destinataire ? ' auprès de '.$this->destinataire : '').'.',
                'due_date' => now()->toDateString(),
                'priorite' => $this->prioriteRelance()->value,
                'statut' => TaskStatut::AFaire->value,
                'source' => 'auto',
                'created_by' => $userId,
                'organisation_id' => $this->organisation_id,
            ],
        );
    }

    /**
     * P1-16-4 : refuse l'émission d'une facture sans montant ni destinataire.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === InvoiceStatut::Emise) {
            if ((float) $this->montant <= 0) {
                return 'Émission impossible : la facture doit porter un montant supérieur à zéro.';
            }

            if (blank($this->destinataire)) {
                return 'Émission impossible : un destinataire est obligatoire.';
            }
        }

        return null;
    }

    /**
     * Effet de bord : au passage à « Émise », on horodate l'émission si elle
     * n'est pas fournie. Le numéro n'est PAS généré par l'ERP : la facture fait
     * foi côté comptabilité (le numéro légal est saisi ou importé de la compta).
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === InvoiceStatut::Emise && $this->date_emission === null) {
            $this->forceFill(['date_emission' => now()->toDateString()])->saveQuietly();
        }

        // Facture soldée ou annulée : on clôt la relance d'impayé éventuelle.
        if (in_array($to, [InvoiceStatut::Payee, InvoiceStatut::Annulee], true)) {
            $this->tasks()
                ->where('cle', $this->cleRelance())
                ->whereNotIn('statut', [TaskStatut::Terminee->value, TaskStatut::Annulee->value])
                ->update(['statut' => TaskStatut::Terminee->value]);
        }
    }
}
