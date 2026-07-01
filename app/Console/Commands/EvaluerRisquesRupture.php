<?php

namespace App\Console\Commands;

use App\Services\RuptureRiskService;
use Illuminate\Console\Command;

/**
 * Recalcule le score de risque de rupture de tous les contrats en cours et
 * persiste l'instantané. Planifiée quotidiennement, avant la génération des
 * alertes (qui s'appuie sur les niveaux de risque stockés).
 */
class EvaluerRisquesRupture extends Command
{
    protected $signature = 'app:evaluer-risques';

    protected $description = 'Recalcule le risque de rupture des contrats en cours';

    public function handle(RuptureRiskService $service): int
    {
        $aRisque = $service->evaluerTous();

        $this->info($aRisque.' contrat(s) à risque élevé ou critique de rupture.');

        return self::SUCCESS;
    }
}
