<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\StateMachine\ManagesState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Dossier de rupture d'un contrat (EPIC-18). À l'ouverture, la rupture propage
 * automatiquement une trace dans le contrat (→ Rompu), le candidat (→ Rupture)
 * et crée des actions de suivi côté OPCO et finance (P1-18-3), conservées comme
 * preuves.
 */
class Rupture extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_rupture' => 'date',
            'motif' => RuptureMotif::class,
            'statut' => RuptureStatut::class,
        ];
    }

    protected static function booted(): void
    {
        // Propagation des traces à l'ouverture du dossier (P1-18-3).
        static::created(fn (Rupture $rupture) => $rupture->propagerTraces());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'motif', 'date_rupture', 'nouvel_employeur'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('rupture');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /**
     * Propage la rupture : statut du contrat et du candidat, puis actions de
     * suivi OPCO et finance (idempotentes via leur clé).
     */
    public function propagerTraces(): void
    {
        $contract = $this->contract;

        if (! $contract) {
            return;
        }

        // Contrat → Rompu (si la transition est permise depuis l'état courant).
        if ($contract->canTransitionTo(ContractStatut::Rompu)) {
            $contract->transitionTo(ContractStatut::Rompu, 'Rupture ouverte le '.$this->date_rupture?->format('d/m/Y'));
        }

        // Candidat → Rupture.
        $candidate = $contract->candidate;
        if ($candidate && $candidate->canTransitionTo(CandidateStatut::Rupture)) {
            $candidate->transitionTo(CandidateStatut::Rupture, 'Rupture du contrat #'.$contract->id);
        }

        // Trace OPCO : action de traitement du financement.
        if ($contract->opcoFile) {
            $this->creerAction(
                $contract->opcoFile,
                'rupture:opco:'.$this->id,
                'Rupture — traiter le dossier OPCO',
                'Le contrat a été rompu : arrêter / recalculer le financement OPCO.',
            );
        }

        // Trace finance : revue de la facturation.
        if ($contract->financeLines()->exists()) {
            $this->creerAction(
                $contract,
                'rupture:finance:'.$this->id,
                'Rupture — revoir la facturation',
                'Le contrat a été rompu : ajuster les montants dus / facturés au prorata.',
            );
        }
    }

    /** Crée (idempotemment) une tâche de suivi rattachée à un objet. */
    private function creerAction(Model $taskable, string $cle, string $titre, string $description): void
    {
        $taskable->tasks()->updateOrCreate(
            ['cle' => $cle],
            [
                'titre' => $titre,
                'description' => $description,
                'assignee_id' => $this->created_by,
                'created_by' => $this->created_by,
                'due_date' => now()->addDays(7),
                'priorite' => TaskPriorite::Haute->value,
                'statut' => TaskStatut::AFaire->value,
                'source' => 'auto',
            ],
        );
    }
}
