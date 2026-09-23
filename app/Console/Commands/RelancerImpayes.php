<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatut;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Relance des factures impayées (Phase C). Balaie les factures émises, échues et
 * non soldées — tous CFA confondus (aucun tenant en contexte planifié) — et ouvre
 * une tâche de relance par facture. Idempotent : la tâche est ré-actualisée sans
 * doublon (clé {@see Invoice::cleRelance()}), avec une priorité qui s'escalade
 * selon l'ancienneté du retard. Planifiée quotidiennement.
 */
class RelancerImpayes extends Command
{
    protected $signature = 'finance:relancer-impayes';

    protected $description = 'Ouvre/actualise les tâches de relance des factures impayées échues';

    public function handle(): int
    {
        $factures = Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->with('financeLine.contract.candidate')
            ->get()
            // resteAPayer() n'est pas calculable en SQL : on écarte ici les
            // factures partiellement soldées mais déjà couvertes.
            ->filter(fn (Invoice $facture): bool => $facture->estEnRetard());

        $factures->each(fn (Invoice $facture) => $facture->ouvrirRelance());

        $this->info($factures->count().' relance(s) d\'impayé ouverte(s) ou actualisée(s).');

        return self::SUCCESS;
    }
}