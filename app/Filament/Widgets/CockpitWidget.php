<?php

namespace App\Filament\Widgets;

use App\Services\CockpitData;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Cockpit de supervision : le « héros » du tableau de bord d'accueil.
 *
 * Un unique widget pleine largeur qui compose tout le centre de supervision —
 * résumé intelligent, cartes KPI, entonnoir, évolution, répartition, priorités,
 * alertes, agenda et activité. Toutes les données proviennent de {@see CockpitData}
 * (aucune donnée fictive). La présentation vit dans la vue Blade associée.
 */
class CockpitWidget extends Widget
{
    protected string $view = 'filament.widgets.cockpit';

    protected static ?int $sort = -100;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check();
    }

    protected function getViewData(): array
    {
        $data = app(CockpitData::class);

        return [
            'insights' => $data->insights(),
            'kpis' => $data->kpis(),
            'pipeline' => $data->pipeline(),
            'evolution' => $data->evolution(),
            'distribution' => $data->contractDistribution(),
            'priorites' => $data->priorityTasks(),
            'alertes' => $data->criticalAlerts(),
            'agenda' => $data->agenda(),
            'activite' => $data->recentActivity(),
            'utilisateur' => Auth::user()?->name ?? '',
        ];
    }
}
