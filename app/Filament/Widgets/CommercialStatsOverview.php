<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\TaskStatut;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Matchings\MatchingResource;
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
 * Cockpit commercial (P0-12-2) : les indicateurs de pilotage quotidien du
 * commercial connecté — placement des candidats, avancement des propositions
 * et actions à mener. Chaque carte est cliquable vers la liste filtrée.
 */
class CommercialStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Cockpit commercial';

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
            ->whereNot('statut', CandidateStatut::Refuse->value)->count();

        $aRappeler = Interaction::query()->relanceDue()->where('user_id', $userId)->count();

        $entretiensAPlanifier = $this->compteCandidats($userId, CandidateStatut::EntretienAPlanifier);
        $entretiensAMener = $this->compteCandidats($userId, CandidateStatut::EntretienPrevu);
        $contratsSignes = Candidate::where('commercial_id', $userId)
            ->whereHas('contracts', fn ($q) => $q->whereIn(
                'statut_contrat',
                \App\Enums\ContractStatut::enCours(),
            ))->count();

        $besoinsOuverts = Need::query()->ouverts()->count();

        $enCours = [
            MatchingStatut::EnRecherche->value,
            MatchingStatut::PropositionEnvoyee->value,
            MatchingStatut::EntretienEntreprise->value,
        ];
        $matchingsEnCours = Matching::where('assigned_by', $userId)->whereIn('statut', $enCours)->count();
        $propositions = $this->compteMatchings($userId, MatchingStatut::PropositionEnvoyee);
        $entretiens = $this->compteMatchings($userId, MatchingStatut::EntretienEntreprise);
        $acceptes = $this->compteMatchings($userId, MatchingStatut::Accepte);

        $tachesEnRetard = Task::where('assignee_id', $userId)
            ->where('statut', TaskStatut::EnRetard->value)->count();

        return [
            Stat::make('Candidats actifs', $mesCandidats)
                ->description('Mes candidats, hors ruptures')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->url(CandidateResource::getUrl()),
            Stat::make('À rappeler', $aRappeler)
                ->description('Relances datées échues')
                ->descriptionIcon('heroicon-m-phone-arrow-up-right')
                ->color($aRappeler > 0 ? 'danger' : 'success'),
            Stat::make('Entretiens à planifier', $entretiensAPlanifier)
                ->description('Candidats sans créneau fixé')
                ->descriptionIcon('heroicon-m-clock')
                ->color($entretiensAPlanifier > 0 ? 'danger' : 'gray')
                ->url($this->candidatsUrl(CandidateStatut::EntretienAPlanifier)),
            Stat::make('Entretiens prévus', $entretiensAMener)
                ->description('Décision CFA attendue')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($entretiensAMener > 0 ? 'warning' : 'gray')
                ->url($this->candidatsUrl(CandidateStatut::EntretienPrevu)),
            Stat::make('Besoins ouverts', $besoinsOuverts)
                ->description('Postes à pourvoir')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning')
                ->url(NeedResource::getUrl()),
            Stat::make('Matchings en cours', $matchingsEnCours)
                ->description('Propositions que je suis')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('primary')
                ->url(MatchingResource::getUrl()),
            Stat::make('Propositions envoyées', $propositions)
                ->description('En attente de suite')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color($propositions > 0 ? 'info' : 'gray')
                ->url($this->matchingsUrl(MatchingStatut::PropositionEnvoyee)),
            Stat::make('Entretiens entreprise', $entretiens)
                ->description('À préparer')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($entretiens > 0 ? 'warning' : 'gray')
                ->url($this->matchingsUrl(MatchingStatut::EntretienEntreprise)),
            Stat::make('Matchings acceptés', $acceptes)
                ->description('Contrat à créer')
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color($acceptes > 0 ? 'success' : 'gray')
                ->url($this->matchingsUrl(MatchingStatut::Accepte)),
            Stat::make('Contrats signés', $contratsSignes)
                ->description('Candidats placés')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(CandidateResource::getUrl()),
            Stat::make('Tâches en retard', $tachesEnRetard)
                ->description('À traiter en priorité')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($tachesEnRetard > 0 ? 'danger' : 'gray')
                ->url(TaskResource::getUrl()),
        ];
    }

    private function compteCandidats(?int $userId, CandidateStatut $statut): int
    {
        return Candidate::where('commercial_id', $userId)->where('statut', $statut->value)->count();
    }

    private function compteMatchings(?int $userId, MatchingStatut $statut): int
    {
        return Matching::where('assigned_by', $userId)->where('statut', $statut->value)->count();
    }

    /** Lien vers la liste des candidats filtrée par statut (deep-link Filament). */
    private function candidatsUrl(CandidateStatut $statut): string
    {
        return CandidateResource::getUrl('index', ['filters' => ['statut' => ['value' => $statut->value]]]);
    }

    /** Lien vers la liste des matchings filtrée par statut. */
    private function matchingsUrl(MatchingStatut $statut): string
    {
        return MatchingResource::getUrl('index', ['filters' => ['statut' => ['value' => $statut->value]]]);
    }
}
