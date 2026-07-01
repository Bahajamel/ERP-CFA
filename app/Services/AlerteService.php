<?php

namespace App\Services;

use App\Enums\AdmissionStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\RiskLevel;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Moteur d'alertes proactives : détecte les situations à risque et crée des
 * tâches (source « auto »), idempotentes via une clé unique, avec notification
 * in-app à la personne concernée. C'est l'antidote aux « ruptures de process ».
 */
class AlerteService
{
    /** Génère toutes les alertes et retourne le nombre de nouvelles créées. */
    public function genererAlertes(): int
    {
        $nouvelles = $this->admissionsIncompletes()
            + $this->contratsASigner()
            + $this->opcoSansRetour()
            + $this->echeancesAVenir()
            + $this->risquesRupture();

        $this->flaggerTachesEnRetard();

        return $nouvelles;
    }

    private function admissionsIncompletes(): int
    {
        $n = 0;

        Admission::query()
            ->whereNotIn('statut', [AdmissionStatut::Valide->value, AdmissionStatut::Refuse->value])
            ->with('candidate')
            ->get()
            ->each(function (Admission $admission) use (&$n) {
                if ($admission->piecesObligatoiresManquantes()->isEmpty()) {
                    return;
                }

                $n += (int) $this->creerAlerte(
                    cle: "admission:incomplete:{$admission->id}",
                    titre: 'Dossier d\'admission incomplet — '.($admission->candidate?->nom_complet ?? 'candidat'),
                    description: 'Des pièces obligatoires sont manquantes ou non conformes.',
                    assigneeId: $admission->candidate?->commercial_id,
                    taskable: $admission,
                    priorite: TaskPriorite::Normale,
                );
            });

        return $n;
    }

    private function contratsASigner(): int
    {
        $n = 0;

        Contract::query()
            ->where('statut_contrat', ContractStatut::EnvoyeSignature->value)
            ->with('candidate')
            ->get()
            ->each(function (Contract $contract) use (&$n) {
                $n += (int) $this->creerAlerte(
                    cle: "contrat:signature:{$contract->id}",
                    titre: 'Relancer la signature du contrat — '.($contract->candidate?->nom_complet ?? 'apprenti'),
                    description: 'Le contrat est envoyé pour signature et n\'est pas encore signé.',
                    assigneeId: null,
                    taskable: $contract,
                    priorite: TaskPriorite::Haute,
                );
            });

        return $n;
    }

    private function opcoSansRetour(): int
    {
        $n = 0;

        OpcoFile::query()
            ->whereIn('statut', [OpcoStatut::Depose->value, OpcoStatut::AttenteRetour->value])
            ->whereNotNull('date_depot')
            ->whereDate('date_depot', '<', now()->subDays(30)->toDateString())
            ->get()
            ->each(function (OpcoFile $file) use (&$n) {
                $n += (int) $this->creerAlerte(
                    cle: "opco:sans_retour:{$file->id}",
                    titre: 'Dossier OPCO sans retour depuis plus de 30 jours',
                    description: 'Déposé le '.$file->date_depot->format('d/m/Y').' — relancer l\'OPCO.',
                    assigneeId: $file->responsable_correction_id,
                    taskable: $file,
                    priorite: TaskPriorite::Haute,
                );
            });

        return $n;
    }

    private function echeancesAVenir(): int
    {
        $n = 0;

        OpcoPayment::query()
            ->where('statut', PaymentStatut::Attendu->value)
            ->whereBetween('date_prevue', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->with('opcoFile')
            ->get()
            ->each(function (OpcoPayment $payment) use (&$n) {
                if ($payment->opcoFile === null) {
                    return;
                }

                $n += (int) $this->creerAlerte(
                    cle: "opco:echeance:{$payment->id}",
                    titre: 'Versement OPCO à venir — '.$payment->libelle,
                    description: 'Échéance le '.$payment->date_prevue->format('d/m/Y')
                        .' ('.number_format((float) $payment->montant_prevu, 2, ',', ' ').' €).',
                    assigneeId: $payment->opcoFile->responsable_correction_id,
                    taskable: $payment->opcoFile,
                    priorite: TaskPriorite::Normale,
                );
            });

        return $n;
    }

    /**
     * Alerte sur les contrats à risque élevé/critique de rupture (score calculé
     * en amont par app:evaluer-risques). Une tâche par contrat, priorisée selon
     * le niveau, assignée au commercial qui suit l'apprenti.
     */
    private function risquesRupture(): int
    {
        $n = 0;

        Contract::query()
            ->whereIn('risk_level', RiskLevel::aRisque())
            ->with('candidate')
            ->get()
            ->each(function (Contract $contract) use (&$n) {
                $facteurs = collect($contract->risk_factors ?? [])
                    ->pluck('label')
                    ->implode(' · ');

                $priorite = $contract->risk_level === RiskLevel::Critique
                    ? TaskPriorite::Urgente
                    : TaskPriorite::Haute;

                $n += (int) $this->creerAlerte(
                    cle: "rupture:risque:{$contract->id}",
                    titre: 'Risque de rupture ('.$contract->risk_level->getLabel().') — '
                        .($contract->candidate?->nom_complet ?? 'apprenti'),
                    description: 'Score '.$contract->risk_score.'/100 · '.($facteurs ?: 'facteurs à examiner'),
                    assigneeId: $contract->candidate?->commercial_id,
                    taskable: $contract,
                    priorite: $priorite,
                );
            });

        return $n;
    }

    /** Passe en « En retard » les tâches dont l'échéance est dépassée. */
    private function flaggerTachesEnRetard(): void
    {
        Task::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('statut', [
                TaskStatut::Terminee->value,
                TaskStatut::Annulee->value,
                TaskStatut::EnRetard->value,
            ])
            ->update(['statut' => TaskStatut::EnRetard->value]);
    }

    /** Crée une alerte si sa clé n'existe pas encore. Retourne true si créée. */
    private function creerAlerte(
        string $cle,
        string $titre,
        ?string $description,
        ?int $assigneeId,
        Model $taskable,
        TaskPriorite $priorite,
    ): bool {
        if (Task::query()->where('cle', $cle)->exists()) {
            return false;
        }

        Task::query()->create([
            'cle' => $cle,
            'titre' => $titre,
            'description' => $description,
            'taskable_type' => $taskable::class,
            'taskable_id' => $taskable->getKey(),
            'assignee_id' => $assigneeId,
            'created_by' => null,
            'due_date' => now(),
            'priorite' => $priorite->value,
            'statut' => TaskStatut::AFaire->value,
            'source' => 'auto',
        ]);

        if ($assigneeId !== null && ($user = User::find($assigneeId)) !== null) {
            Notification::make()
                ->title($titre)
                ->body($description)
                ->warning()
                ->sendToDatabase($user);
        }

        return true;
    }
}
