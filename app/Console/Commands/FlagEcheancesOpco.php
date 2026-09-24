<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\OpcoPayment;
use Illuminate\Console\Command;

/**
 * Passe en « En retard » les échéances de versement OPCO dépassées et crée une
 * tâche de relance (une seule fois, à la bascule). Planifiée quotidiennement.
 */
class FlagEcheancesOpco extends Command
{
    protected $signature = 'opco:flag-echeances';

    protected $description = 'Marque les échéances de versement OPCO en retard et crée les relances';

    public function handle(): int
    {
        $enRetard = OpcoPayment::query()
            ->where('statut', PaymentStatut::Attendu->value)
            ->whereDate('date_prevue', '<', now()->toDateString())
            ->with('opcoFile')
            ->get();

        foreach ($enRetard as $payment) {
            $payment->update(['statut' => PaymentStatut::EnRetard->value]);

            $dossier = $payment->opcoFile;

            if ($dossier === null) {
                continue;
            }

            // Idempotent (clé par échéance) et rattaché au CFA du dossier : sans
            // organisation_id explicite, la tâche naîtrait NULL en contexte
            // planifié (aucun tenant courant) et resterait invisible dans l'écran
            // Tâches & Alertes cloisonné par CFA.
            $dossier->tasks()->updateOrCreate(
                ['cle' => "opco:retard:{$payment->id}"],
                [
                    'titre' => 'Relancer le versement OPCO en retard — '.$payment->libelle,
                    'description' => 'Échéance du '.$payment->date_prevue->format('d/m/Y')
                        .' non versée ('.number_format((float) $payment->montant_prevu, 2, ',', ' ').' €).',
                    'assignee_id' => $dossier->responsable_correction_id,
                    'created_by' => null,
                    'due_date' => now(),
                    'priorite' => TaskPriorite::Haute->value,
                    'statut' => TaskStatut::AFaire->value,
                    'source' => 'auto',
                    'organisation_id' => $dossier->organisation_id,
                ],
            );
        }

        $this->info($enRetard->count().' échéance(s) passée(s) en retard.');

        return self::SUCCESS;
    }
}
