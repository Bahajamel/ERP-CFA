<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Parcours\CycleApprenant;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OpcoFile extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_depot' => 'date',
            'date_relance' => 'date',
            'montant_prevu' => 'decimal:2',
            'montant_accepte' => 'decimal:2',
            'statut' => OpcoStatut::class,
        ];
    }

    /**
     * Invariants du cycle apprenant :
     *  - un dossier OPCO n'existe qu'après signature du contrat par les trois
     *    parties (apprenti, entreprise, CFA) — invariant backend ;
     *  - dès qu'un dossier naît « créé / transmis » (imports, seeds…),
     *    l'admission officielle correspondante est ouverte (idempotent).
     */
    protected static function booted(): void
    {
        static::creating(function (self $dossier): void {
            $contract = Contract::query()->find($dossier->contract_id);

            if ($contract === null || ! $contract->estSigne()) {
                throw ValidationException::withMessages([
                    'contract_id' => CycleApprenant::MSG_CONTRAT_NON_SIGNE,
                ]);
            }
        });

        static::created(function (self $dossier): void {
            if (CycleApprenant::opcoOuvreAdmission($dossier->statut)) {
                app(CycleApprenant::class)->ouvrirAdmission($dossier);
            }
        });

        // Même déclencheur pour une mise à jour directe du statut (formulaire,
        // import…) hors machine à états : idempotent via firstOrCreate.
        static::updated(function (self $dossier): void {
            if (! $dossier->wasChanged('statut')) {
                return;
            }

            if (CycleApprenant::opcoOuvreAdmission($dossier->statut)) {
                app(CycleApprenant::class)->ouvrirAdmission($dossier);
            }

            // Dossier rejeté par l'OPCO → le contrat repasse en « À corriger »
            // dans la section Contrats (correction avant nouvelle transmission).
            if ($dossier->statut === OpcoStatut::Rejete) {
                $dossier->retournerContratPourCorrection();
            }
        });
    }

    /**
     * Renvoie le contrat en « À corriger » (section Contrats) suite à un rejet
     * OPCO. Silencieux si la transition n'est pas permise : la cohérence ne
     * doit jamais empêcher l'enregistrement du rejet.
     */
    public function retournerContratPourCorrection(): void
    {
        $contract = $this->contract;

        if ($contract === null || ! $contract->statut_contrat->canTransitionTo(ContractStatut::ACorriger)) {
            return;
        }

        try {
            $contract->transitionTo(ContractStatut::ACorriger, 'Dossier OPCO rejeté : '.$this->motif_rejet);
        } catch (\Throwable) {
            // La cohérence n'empêche jamais l'enregistrement du rejet OPCO.
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant_prevu', 'montant_accepte', 'motif_rejet'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('opco');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function opco(): BelongsTo
    {
        return $this->belongsTo(Opco::class);
    }

    /**
     * OPCO effectif du dossier : celui rattaché au dossier, sinon celui de
     * l'entreprise du contrat (déduit du SIRET dans Entreprises partenaires).
     * Évite un « — » alors que l'OPCO de l'entreprise est déjà connu.
     */
    public function opcoEffectif(): ?Opco
    {
        return $this->opco ?? $this->contract?->company?->opco;
    }

    public function responsableCorrection(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_correction_id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OpcoPayment::class)->orderBy('ordre');
    }

    /**
     * Génère l'échéancier de versement conforme au décret n° 2025-585 :
     * contrats ≥ 12 mois → 40 % (J+30), 30 % (7e mois), 20 % (10e mois), 10 % (solde).
     * Contrats < 12 mois → 50 % (J+30) puis solde. Idempotent (ne régénère pas).
     */
    public function genererEcheancier(): void
    {
        $montant = (float) $this->montant_accepte;

        if ($montant <= 0 || $this->payments()->exists()) {
            return;
        }

        $debut = Carbon::parse($this->contract?->date_debut ?? $this->date_depot ?? now());
        $fin = $this->contract?->date_fin
            ? Carbon::parse($this->contract->date_fin)
            : $debut->copy()->addYear();

        $plan = $debut->diffInMonths($fin) >= 12
            ? [
                ['1er versement (40 %)', 40, $debut->copy()->addDays(30)],
                ['2e versement (30 %)', 30, $debut->copy()->addMonths(7)],
                ['3e versement (20 %)', 20, $debut->copy()->addMonths(10)],
                ['Solde (10 %)', 10, $fin],
            ]
            : [
                ['1er versement (50 %)', 50, $debut->copy()->addDays(30)],
                ['Solde (50 %)', 50, $fin],
            ];

        $cumul = 0.0;
        $dernier = count($plan) - 1;

        foreach ($plan as $i => [$libelle, $pourcentage, $date]) {
            $montantEcheance = $i === $dernier
                ? round($montant - $cumul, 2)
                : round($montant * $pourcentage / 100, 2);
            $cumul += $montantEcheance;

            $this->payments()->create([
                'ordre' => $i + 1,
                'libelle' => $libelle,
                'pourcentage' => $pourcentage,
                'montant_prevu' => $montantEcheance,
                'date_prevue' => $date->toDateString(),
                'statut' => PaymentStatut::Attendu->value,
            ]);
        }
    }

    public function montantVerse(): float
    {
        return (float) $this->payments()->sum('montant_verse');
    }

    public function resteAVerser(): float
    {
        return max(0, (float) $this->montant_accepte - $this->montantVerse());
    }

    /** Le contrat associé est-il signé ? (condition de dépôt OPCO) */
    public function contratEstSigne(): bool
    {
        return $this->contract?->statut_signature === ContractSignatureStatut::Signe;
    }

    /**
     * Règles métier (CDC P0-09-6) :
     * - pas de « Prêt au dépôt » si le contrat n'est pas signé ;
     * - un rejet exige un motif de rejet.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === OpcoStatut::PretDepot && ! $this->contratEstSigne()) {
            return "« Prêt au dépôt » impossible : le contrat associé n'est pas signé.";
        }

        if ($to === OpcoStatut::Rejete && blank($this->motif_rejet)) {
            return 'Un rejet OPCO exige un motif de rejet.';
        }

        return null;
    }

    /**
     * Effets de bord : à chaque rejet, une action corrective (tâche) est créée
     * automatiquement (CDC P0-09-3).
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === OpcoStatut::Rejete) {
            $this->tasks()->create([
                'titre' => 'Corriger le dossier OPCO rejeté',
                'description' => $this->motif_rejet,
                'assignee_id' => $this->responsable_correction_id,
                'created_by' => auth()->id(),
                'due_date' => now()->addDays(7),
                'priorite' => TaskPriorite::Haute->value,
                'statut' => TaskStatut::AFaire->value,
                'source' => 'auto',
            ]);

            // Le contrat repasse en « À corriger » dans la section Contrats.
            $this->retournerContratPourCorrection();
        }

        // Acceptation OPCO : on génère l'échéancier de versement (décret 2025-585).
        if ($to === OpcoStatut::Accepte) {
            $this->genererEcheancier();
        }

        // Cycle apprenant : dossier créé ou transmis pour validation → ouverture
        // de l'admission officielle « À vérifier » (idempotent, jamais supprimée
        // ensuite, même si l'OPCO rejette ou demande correction).
        if (CycleApprenant::opcoOuvreAdmission($to)) {
            app(CycleApprenant::class)->ouvrirAdmission($this);
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire_interne' => $comment])->saveQuietly();
        }
    }
}
