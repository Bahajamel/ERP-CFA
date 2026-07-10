<?php

namespace App\Console\Commands;

use App\Services\FinanceService;
use Illuminate\Console\Command;

/**
 * Synchronise tous les dossiers Contrats & OPCO avec la Finance : une ligne
 * financière par dossier (montant accepté ou prévisionnel) + factures dues.
 * Idempotent — planifiable (ex. quotidien) pour rattraper les dossiers en attente.
 */
class SynchroniserFinance extends Command
{
    protected $signature = 'finance:synchroniser';

    protected $description = 'Synchronise les dossiers Contrats & OPCO avec les lignes financières (idempotent)';

    public function handle(FinanceService $finance): int
    {
        $this->info('Synchronisation des dossiers Contrats & OPCO avec Finance…');

        $s = $finance->synchroniserTousLesDossiers();

        $this->table(
            ['Lignes à jour', 'Factures générées', 'Dossiers ignorés'],
            [[$s['lignes'], $s['factures'], $s['ignores']]],
        );

        $this->info('Terminé.');

        return self::SUCCESS;
    }
}
