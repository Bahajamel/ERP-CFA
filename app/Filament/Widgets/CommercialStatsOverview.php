<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\TaskStatut;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Candidate;
use App\Models\Interaction;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard commercial (P0-12-2) : les indicateurs de pilotage quotidien du
 * commercial connecté — placement des candidats, pipeline besoins/matchs et
 * actions à mener (relances, tâches en retard). Cartes cliquables.
 */
class CommercialStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Mon activité commerciale';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Commercial', 'Administrateur']) ?? false;
    }

    protected function getStats(): array
    {
        $userId = Auth::id();

        $mesCandidats = Candidate::where('commercial_id', $userId)
            ->whereNot('statut', CandidateStatut::Rupture->value)
            ->count();

        $aPlacer = Candidate::where('commercial_id', $userId)
            ->where('statut', CandidateStatut::EnRechercheEntreprise->value)
            ->count();

        $besoinsOuverts = Need::query()->ouverts()->count();

        $propositionsEnCours = Matching::where('assigned_by', $userId)
            ->whereIn('statut', [
                MatchingStatut::Propose->value,
                MatchingStatut::CvEnvoye->value,
                MatchingStatut::EntretienPrevu->value,
                MatchingStatut::AttenteRetour->value,
            ])
            ->count();

        $relancesAFaire = Interaction::query()
            ->relanceDue()
            ->where('user_id', $userId)
            ->count();

        $tachesEnRetard = Task::where('assignee_id', $userId)
            ->where('statut', TaskStatut::EnRetard->value)
            ->count();

        return [
            Stat::make('Mes candidats', $mesCandidats)
                ->description('Suivis, hors ruptures')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->url(CandidateResource::getUrl()),
            Stat::make('À placer', $aPlacer)
                ->description('En recherche d\'entreprise')
                ->descriptionIcon('heroicon-m-fire')
                ->color($aPlacer > 0 ? 'warning' : 'gray')
                ->url(CandidateResource::getUrl()),
            Stat::make('Besoins ouverts', $besoinsOuverts)
                ->description('Postes à pourvoir')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning')
                ->url(NeedResource::getUrl()),
            Stat::make('Propositions en cours', $propositionsEnCours)
                ->description('Candidats que j\'ai proposés')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('primary'),
            Stat::make('Relances à faire', $relancesAFaire)
                ->description('Actions datées échues')
                ->descriptionIcon('heroicon-m-phone-arrow-up-right')
                ->color($relancesAFaire > 0 ? 'danger' : 'success'),
            Stat::make('Mes tâches en retard', $tachesEnRetard)
                ->description('À traiter en priorité')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($tachesEnRetard > 0 ? 'danger' : 'gray')
                ->url(TaskResource::getUrl()),
        ];
    }
}
