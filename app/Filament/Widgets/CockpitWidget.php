<?php

namespace App\Filament\Widgets;

use App\Services\CockpitData;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Cockpit de supervision : le « héros » du tableau de bord d'accueil.
 *
 * Un unique widget pleine largeur qui compose tout le centre de supervision —
 * onglets départements, résumé intelligent, cartes KPI, entonnoir, évolution,
 * répartition, priorités, alertes, agenda et activité. Toutes les données
 * proviennent de {@see CockpitData} (aucune donnée fictive).
 *
 * Interactif : le filtre de période (3/6/12 mois) recalcule les mini-courbes et
 * l'évolution ; le bouton « Demander à l'IA » synthétise le briefing du jour.
 */
class CockpitWidget extends Widget
{
    protected string $view = 'filament.widgets.cockpit';

    protected static ?int $sort = -100;

    protected int|string|array $columnSpan = 'full';

    /** Fenêtre d'historique retenue (nombre de mois : « 3 », « 6 » ou « 12 »). */
    public string $periode = '6';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /** Change la période affichée (mini-courbes + évolution). */
    public function definirPeriode(string $mois): void
    {
        $this->periode = in_array($mois, ['3', '6', '12'], true) ? $mois : '6';
    }

    /** « Demander à l'IA » : synthèse du jour calculée en direct sur les données. */
    public function demanderIA(): void
    {
        $i = app(CockpitData::class)->insights();

        $corps = $i['a_retenir']."\n\n"
            .'Anomalies : '.implode(' ', $i['anomalies'])."\n\n"
            .'Recommandations : '.implode(' ', $i['recommandations']);

        Notification::make()
            ->title('Briefing de supervision — '.now()->translatedFormat('l j F'))
            ->body($corps)
            ->icon('heroicon-o-sparkles')
            ->iconColor('primary')
            ->persistent()
            ->send();
    }

    protected function getViewData(): array
    {
        $data = app(CockpitData::class)->periode((int) $this->periode);

        return [
            'departements' => $data->departements(),
            'insights' => $data->insights(),
            'kpis' => $data->kpis(),
            'pipeline' => $data->pipeline(),
            'evolution' => $data->evolution(),
            'distribution' => $data->contractDistribution(),
            'priorites' => $data->priorityTasks(),
            'alertes' => $data->criticalAlerts(),
            'agenda' => $data->agenda(),
            'activite' => $data->recentActivity(),
            'periode' => $this->periode,
            'utilisateur' => Auth::user()?->name ?? '',
        ];
    }
}
