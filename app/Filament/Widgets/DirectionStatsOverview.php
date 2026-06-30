<?php

namespace App\Filament\Widgets;

use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Enums\TaskStatut;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DirectionStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Vue direction';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $candidatsActifs = Candidate::whereNot('statut', CandidateStatut::Rupture->value)->count();
        $besoinsOuverts = Need::whereIn('statut', [
            NeedStatut::Cree->value,
            NeedStatut::EnQualification->value,
            NeedStatut::ProfilsRecherches->value,
            NeedStatut::ProfilsEnvoyes->value,
            NeedStatut::EntretienPrevu->value,
            NeedStatut::CandidatRetenu->value,
        ])->count();
        $contratsSignes = Contract::whereIn('statut_contrat', [
            ContractStatut::Signe->value,
            ContractStatut::TransmisOpco->value,
            ContractStatut::Actif->value,
        ])->count();
        $opcoBloques = OpcoFile::whereIn('statut', OpcoStatut::bloques())->count();
        $montantAttendu = (float) OpcoFile::sum('montant_prevu');
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
                ->color('info'),
            Stat::make('Besoins ouverts', $besoinsOuverts)
                ->description('Postes à pourvoir')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),
            Stat::make('Contrats signés', $contratsSignes)
                ->description('Signés / transmis / actifs')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Dossiers OPCO bloqués', $opcoBloques)
                ->description('Rejetés ou en correction')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($opcoBloques > 0 ? 'danger' : 'success'),
            Stat::make('Montant attendu OPCO', number_format($montantAttendu, 0, ',', ' ').' €')
                ->description('Financement prévisionnel')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make('Tâches ouvertes', $tachesOuvertes)
                ->description('À traiter par les équipes')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('gray'),
        ];
    }
}
