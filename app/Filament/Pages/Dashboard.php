<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionStatsOverview;
use App\Filament\Widgets\CommercialStatsOverview;
use App\Filament\Widgets\DirectionStatsOverview;
use App\Filament\Widgets\DossiersAdmissionTable;
use App\Filament\Widgets\MesActionsStats;
use App\Filament\Widgets\MesTachesTable;
use App\Filament\Widgets\OpcoBloquesTable;
use App\Filament\Widgets\PrioritesDuJourWidget;
use App\Filament\Widgets\RelancesCommercialesTable;
use App\Filament\Widgets\TachesPrioritairesTable;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

/**
 * Tableau de bord épuré : n'affiche que l'essentiel actionnable (activité et
 * tâches personnelles, compteurs par rôle, listes d'actions à traiter).
 *
 * La liste est explicite (et non l'auto-découverte) pour maîtriser ce qui
 * s'affiche : les graphiques et cartes KPI secondaires restent disponibles
 * ailleurs (en-têtes de pages) mais ne surchargent plus le dashboard. Chaque
 * widget conserve son propre `canView()` : l'affichage reste filtré par rôle.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            PrioritesDuJourWidget::class,
            MesActionsStats::class,
            DirectionStatsOverview::class,
            CommercialStatsOverview::class,
            AdmissionStatsOverview::class,
            TachesPrioritairesTable::class,
            OpcoBloquesTable::class,
            DossiersAdmissionTable::class,
            RelancesCommercialesTable::class,
            MesTachesTable::class,
        ];
    }
}
