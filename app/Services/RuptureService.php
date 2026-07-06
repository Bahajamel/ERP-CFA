<?php

namespace App\Services;

use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Contract;
use App\Models\RuptureCase;
use App\Models\Task;
use App\StateMachine\InvalidTransitionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Orchestration métier de la rupture d'un contrat d'apprentissage (EPIC-18).
 *
 * Ouvrir un dossier fait plus que créer une ligne : c'est le point où la rupture
 * se propage au reste du dossier apprenant. Le contrat passe à « Rompu », et une
 * trace (tâche) est créée côté OPCO et côté Finance pour régulariser le
 * financement — les concurrents laissent ces régularisations se perdre en Excel.
 */
class RuptureService
{
    /**
     * Ouvre (ou récupère) le dossier de rupture d'un contrat. Idempotent :
     * un seul dossier par contrat.
     *
     * @param  array{motif?:RuptureMotif|string,initiateur?:RuptureInitiateur|string,date_rupture?:string,motif_detail?:?string,responsable_id?:?int}  $data
     */
    public function ouvrir(Contract $contract, array $data = []): RuptureCase
    {
        return DB::transaction(function () use ($contract, $data) {
            $dossier = $contract->ruptureCase()->first();

            if ($dossier === null) {
                $dossier = $contract->ruptureCase()->create([
                    'statut' => RuptureStatut::Ouvert->value,
                    'date_rupture' => $data['date_rupture'] ?? now()->toDateString(),
                    'motif' => ($data['motif'] ?? RuptureMotif::Autre) instanceof RuptureMotif
                        ? ($data['motif'] ?? RuptureMotif::Autre)->value
                        : $data['motif'],
                    'initiateur' => ($data['initiateur'] ?? RuptureInitiateur::CommunAccord) instanceof RuptureInitiateur
                        ? ($data['initiateur'] ?? RuptureInitiateur::CommunAccord)->value
                        : $data['initiateur'],
                    'motif_detail' => $data['motif_detail'] ?? null,
                    'responsable_id' => $data['responsable_id'] ?? auth()->id(),
                ]);
            }

            $this->passerContratRompu($contract);
            $this->tracerCascade($contract);

            return $dossier;
        });
    }

    /** Passe le contrat à « Rompu » si la machine à états l'autorise. */
    private function passerContratRompu(Contract $contract): void
    {
        if ($contract->statut_contrat === ContractStatut::Rompu) {
            return;
        }

        try {
            $contract->transitionTo(ContractStatut::Rompu, 'Rupture du contrat — dossier ouvert.');
        } catch (InvalidTransitionException) {
            // Contrat pas encore engagé (brouillon…) : le dossier existe quand
            // même comme trace, mais on ne force pas une transition invalide.
        }
    }

    /**
     * Propage la rupture au financement : une tâche de régularisation côté OPCO
     * (arrêt/ajustement du dossier en cours) et côté Finance (arrêt de la
     * facturation, régularisation des montants). Idempotent par clé.
     */
    private function tracerCascade(Contract $contract): void
    {
        $apprenti = $contract->candidate?->nom_complet ?? 'apprenti';

        $opcoFile = $contract->opcoFile;
        if ($opcoFile !== null && $opcoFile->statut !== OpcoStatut::Cloture) {
            $this->creerTache(
                cle: "rupture:opco:{$contract->id}",
                titre: "Régulariser le dossier OPCO — rupture ({$apprenti})",
                description: 'Le contrat est rompu : ajuster ou clôturer le dossier OPCO en cours '
                    .'(financement au prorata, arrêt des versements).',
                taskable: $opcoFile,
            );
        }

        if ($contract->financeLines()->exists()) {
            $this->creerTache(
                cle: "rupture:finance:{$contract->id}",
                titre: "Régulariser la facturation — rupture ({$apprenti})",
                description: 'Le contrat est rompu : arrêter la facturation, régulariser les montants '
                    .'attendus/facturés et bloquer les lignes concernées avec le motif « rupture ».',
                taskable: $contract,
            );
        }
    }

    /** Crée une tâche de régularisation si sa clé n'existe pas déjà. */
    private function creerTache(string $cle, string $titre, string $description, Model $taskable): void
    {
        if (Task::query()->where('cle', $cle)->exists()) {
            return;
        }

        Task::query()->create([
            'cle' => $cle,
            'titre' => $titre,
            'description' => $description,
            'taskable_type' => $taskable::class,
            'taskable_id' => $taskable->getKey(),
            'assignee_id' => null,
            'created_by' => auth()->id(),
            'due_date' => now()->addWeek()->toDateString(),
            'priorite' => TaskPriorite::Haute->value,
            'statut' => TaskStatut::AFaire->value,
            'source' => 'auto',
        ]);
    }
}
