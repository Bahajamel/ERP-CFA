<?php

namespace App\Console\Commands;

use App\Services\AlerteService;
use Illuminate\Console\Command;

/**
 * Génère les alertes proactives de l'ERP (dossiers incomplets, contrats à
 * signer, OPCO sans retour, échéances à venir) et flague les tâches en retard.
 * Planifiée quotidiennement.
 */
class GenererAlertes extends Command
{
    protected $signature = 'app:generer-alertes';

    protected $description = 'Génère les alertes proactives et met à jour les tâches en retard';

    public function handle(AlerteService $service): int
    {
        $nouvelles = $service->genererAlertes();

        $this->info($nouvelles.' nouvelle(s) alerte(s) générée(s).');

        return self::SUCCESS;
    }
}
