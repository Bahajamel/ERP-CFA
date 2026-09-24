<?php

namespace App\Services;

use App\Enums\ContractStatut;
use App\Enums\InvoiceStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\QualiopiStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\QualiopiIndicator;
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
        $nouvelles = $this->contratsASigner()
            + $this->opcoSansRetour()
            + $this->echeancesAVenir()
            + $this->facturesEnRetard()
            + $this->nonConformitesQualiopi();

        $this->flaggerTachesEnRetard();

        return $nouvelles;
    }

    private function contratsASigner(): int
    {
        $n = 0;

        Contract::query()
            ->where('statut_contrat', ContractStatut::ManqueSignature->value)
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
     * Relance des factures impayées : facture émise, échue et non soldée →
     * tâche de relance (priorité selon l'ancienneté du retard).
     */
    private function facturesEnRetard(): int
    {
        $n = 0;

        Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->with('financeLine.contract.candidate')
            ->get()
            ->each(function (Invoice $invoice) use (&$n) {
                if ($invoice->resteAPayer() <= 0) {
                    return;
                }

                $joursRetard = $invoice->date_echeance->diffInDays(now());
                $priorite = $joursRetard >= 30 ? TaskPriorite::Urgente : TaskPriorite::Haute;

                $n += (int) $this->creerAlerte(
                    cle: "finance:impaye:{$invoice->id}",
                    titre: 'Facture impayée à relancer — '.($invoice->numero ?: 'brouillon #'.$invoice->id),
                    description: 'Échue le '.$invoice->date_echeance->format('d/m/Y')
                        .' ('.(int) $joursRetard.' j de retard) · reste '
                        .number_format($invoice->resteAPayer(), 2, ',', ' ').' € · '
                        .($invoice->destinataire ?? ''),
                    assigneeId: $invoice->created_by,
                    taskable: $invoice,
                    priorite: $priorite,
                );
            });

        return $n;
    }

    /**
     * Alerte sur les indicateurs Qualiopi non conformes : une tâche par indicateur,
     * assignée à son responsable, pour préparer l'audit en continu.
     */
    private function nonConformitesQualiopi(): int
    {
        $n = 0;

        QualiopiIndicator::query()
            ->where('statut', QualiopiStatut::NonConforme->value)
            ->get()
            ->each(function (QualiopiIndicator $indicateur) use (&$n) {
                $n += (int) $this->creerAlerte(
                    cle: "qualiopi:nonconforme:{$indicateur->id}",
                    titre: 'Qualiopi non conforme — indicateur '.$indicateur->numero,
                    description: $indicateur->libelle,
                    assigneeId: $indicateur->responsable_id,
                    taskable: $indicateur,
                    priorite: TaskPriorite::Haute,
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
            // Rattachement au CFA repris de l'objet source : en contexte planifié
            // (CLI, sans tenant courant), sinon la tâche naîtrait avec
            // organisation_id NULL et resterait invisible dans l'écran cloisonné.
            'organisation_id' => $taskable->getAttribute('organisation_id'),
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
