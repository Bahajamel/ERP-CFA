<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatut;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Centre d'actions personnel : la charge de travail de l'utilisateur connecté
 * (tâches à faire, en retard, terminées ce mois) et ses notifications non lues.
 * Visible par tout utilisateur authentifié, en tête du tableau de bord.
 */
class MesActionsStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Mon activité';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /** Statuts d'une tâche encore à traiter. */
    private const OUVERTS = [
        TaskStatut::AFaire->value,
        TaskStatut::EnCours->value,
        TaskStatut::EnAttente->value,
        TaskStatut::EnRetard->value,
    ];

    protected function getStats(): array
    {
        $userId = Auth::id();

        $aFaire = Task::where('assignee_id', $userId)
            ->whereIn('statut', self::OUVERTS)
            ->count();

        $enRetard = Task::where('assignee_id', $userId)
            ->whereIn('statut', self::OUVERTS)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $termineesMois = Task::where('assignee_id', $userId)
            ->where('statut', TaskStatut::Terminee->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $notifs = Auth::user()->unreadNotifications()->count();

        return [
            Stat::make('À faire', $aFaire)
                ->description('Mes tâches ouvertes')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($aFaire > 0 ? 'info' : 'gray'),
            Stat::make('En retard', $enRetard)
                ->description('Échéance dépassée')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($enRetard > 0 ? 'danger' : 'success'),
            Stat::make('Terminées ce mois', $termineesMois)
                ->description('Bravo 👏')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Notifications', $notifs)
                ->description('Non lues')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($notifs > 0 ? 'warning' : 'gray'),
        ];
    }
}
