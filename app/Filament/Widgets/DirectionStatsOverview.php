<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\TaskStatut;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Vue de pilotage de la direction : les 6 indicateurs clés du CFA.
 * Chaque carte est cliquable et renvoie vers la liste filtrée correspondante
 * (« KPI cliquables » — priorité de l'audit concurrentiel).
 * Réservée à la Direction et à l'Administrateur.
 */
class DirectionStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Vue direction';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getStats(): array
    {
        $candidatsActifs = Candidate::whereNot('statut', CandidateStatut::Refuse->value)->count();
        $besoinsOuverts = Need::whereIn('statut', [
            NeedStatut::Cree->value,
            NeedStatut::EnQualification->value,
            NeedStatut::ProfilsRecherches->value,
            NeedStatut::ProfilsEnvoyes->value,
            NeedStatut::EntretienPrevu->value,
            NeedStatut::CandidatRetenu->value,
        ])->count();
        $contratsSignes = Contract::whereIn('statut_contrat', ContractStatut::signes())->count();
        $opcoBloques = OpcoFile::whereIn('statut', OpcoStatut::bloques())->count();
        $montantAttendu = (float) OpcoFile::sum('montant_prevu');
        // Le montant réellement tombé, pas celui qui était prévu : l'OPCO verse
        // couramment moins (proratisation, rupture en cours d'année). La fiche du
        // dossier disait 3 200 €, ce tableau de bord 5 000 € — même versement.
        $montantVerse = (float) OpcoPayment::where('statut', PaymentStatut::Verse->value)->sum('montant_verse');
        $tachesOuvertes = Task::whereIn('statut', [
            TaskStatut::AFaire->value,
            TaskStatut::EnCours->value,
            TaskStatut::EnAttente->value,
            TaskStatut::EnRetard->value,
        ])->count();

        return [
            Stat::make('Candidats actifs', $candidatsActifs)
                ->description('Hors ruptures')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->url(CandidateResource::getUrl()),
            Stat::make('Besoins ouverts', $besoinsOuverts)
                ->description('Postes à pourvoir')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning')
                ->url(NeedResource::getUrl()),
            Stat::make('Contrats signés', $contratsSignes)
                ->description('Signés / transmis / actifs')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(ContractResource::getUrl()),
            Stat::make('Dossiers OPCO bloqués', $opcoBloques)
                ->description('Rejetés ou en correction')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($opcoBloques > 0 ? 'danger' : 'success')
                ->url(OpcoFileResource::getUrl()),
            Stat::make('Financement OPCO encaissé', number_format($montantVerse, 0, ',', ' ').' €')
                ->description(number_format($montantAttendu, 0, ',', ' ').' € attendus au total')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url(OpcoFileResource::getUrl()),
            Stat::make('Tâches ouvertes', $tachesOuvertes)
                ->description('À traiter par les équipes')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('gray')
                ->url(TaskResource::getUrl()),
        ];
    }
}
