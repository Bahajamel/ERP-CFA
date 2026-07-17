<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CockpitWidget;
use App\Filament\Widgets\DossiersAdmissionTable;
use App\Filament\Widgets\MesTachesTable;
use App\Filament\Widgets\OpcoBloquesTable;
use App\Filament\Widgets\RelancesCommercialesTable;
use App\Filament\Widgets\TachesPrioritairesTable;
use App\Services\CockpitData;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;

/**
 * Tableau de bord « cockpit de supervision ».
 *
 * Le héros est le {@see CockpitWidget} pleine largeur : résumé intelligent,
 * cartes KPI à mini-courbes, entonnoir, évolution, répartition, priorités,
 * alertes, agenda et activité — le tout branché sur les données réelles via
 * {@see CockpitData}. Les listes actionnables (à traiter)
 * restent en dessous. Les anciens widgets KPI/graphiques restent disponibles
 * en tant que classes (en-têtes de pages, tests) mais ne surchargent plus
 * l'accueil. Chaque widget conserve son `canView()` (filtrage par rôle).
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            CockpitWidget::class,
            TachesPrioritairesTable::class,
            OpcoBloquesTable::class,
            DossiersAdmissionTable::class,
            RelancesCommercialesTable::class,
            MesTachesTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    /** Cockpit pleine largeur : on occupe tout l'espace (fini les grandes marges). */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
