<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\OpcoStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    public function responsableCorrection(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_correction_id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
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
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire_interne' => $comment])->saveQuietly();
        }
    }
}
