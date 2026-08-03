<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Presence;
use App\Models\Seance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Cartes KPI en tête de « Séances & émargement » : pilotage opérationnel du mois
 * (volume, à venir, à compléter, absences, taux de présence, feuilles et
 * signatures manquantes). Cloisonné par CFA — les séances portent le scope
 * d'organisation, et les présences sont filtrées via leur séance (whereHas).
 */
class SeancesStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('access_attendance') ?? false;
    }

    protected function getStats(): array
    {
        $aujourdhui = Carbon::today();
        $debutMois = Carbon::now()->startOfMonth();
        $finMois = Carbon::now()->endOfMonth();

        $totalMois = Seance::whereBetween('date', [$debutMois, $finMois])->count();

        $aVenir = Seance::whereBetween('date', [$aujourdhui, $aujourdhui->copy()->addDays(7)])
            ->where('statut', '!=', SeanceStatut::Annulee->value)
            ->count();

        $enCours = Seance::whereDate('date', $aujourdhui)
            ->where('statut', SeanceStatut::Planifiee->value)
            ->count();

        $aCompleter = Seance::whereDate('date', '<', $aujourdhui)
            ->where('statut', '!=', SeanceStatut::Annulee->value)
            ->whereHas('presences', fn ($q) => $q->where('statut', PresenceStatut::NonRenseigne->value))
            ->count();

        $absencesInjustifiees = Presence::where('statut', PresenceStatut::AbsentInjustifie->value)
            ->whereHas('seance', fn ($q) => $q->whereBetween('date', [$debutMois, $finMois]))
            ->count();

        // Taux de présence du mois : présents / présences renseignées.
        $renseignees = Presence::where('statut', '!=', PresenceStatut::NonRenseigne->value)
            ->whereHas('seance', fn ($q) => $q->whereBetween('date', [$debutMois, $finMois]))
            ->count();
        $presents = Presence::whereIn('statut', array_map(fn (PresenceStatut $s) => $s->value, PresenceStatut::presents()))
            ->whereHas('seance', fn ($q) => $q->whereBetween('date', [$debutMois, $finMois]))
            ->count();
        $taux = $renseignees > 0 ? round($presents / $renseignees * 100, 1) : null;

        $feuillesManquantes = Seance::whereDate('date', '<', $aujourdhui)
            ->where('statut', '!=', SeanceStatut::Annulee->value)
            ->whereDoesntHave('documents', fn ($q) => $q->where('type', DocumentType::FeuilleEmargement->value))
            ->count();

        $signaturesEnAttente = Presence::whereNotNull('signature_token')
            ->whereNull('signed_at')
            ->whereHas('seance')
            ->count();

        return [
            Stat::make('Total séances', $totalMois)
                ->description('Ce mois')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(SeanceResource::getUrl()),

            Stat::make('À venir', $aVenir)
                ->description('7 prochains jours')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info')
                ->url(SeanceResource::getUrl()),

            Stat::make('En cours', $enCours)
                ->description("Aujourd'hui")
                ->descriptionIcon('heroicon-m-play-circle')
                ->color($enCours > 0 ? 'info' : 'gray')
                ->url(SeanceResource::getUrl()),

            Stat::make('À compléter', $aCompleter)
                ->description('Présences manquantes')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($aCompleter > 0 ? 'warning' : 'success')
                ->url(SeanceResource::getUrl()),

            Stat::make('Absences injustifiées', $absencesInjustifiees)
                ->description('Ce mois · tâches de suivi générées')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($absencesInjustifiees > 0 ? 'danger' : 'success')
                ->url(SeanceResource::getUrl()),

            Stat::make('Taux de présence', $taux === null ? '—' : $taux.' %')
                ->description('Ce mois')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color(match (true) {
                    $taux === null => 'gray',
                    $taux >= 90 => 'success',
                    $taux >= 70 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Feuilles manquantes', $feuillesManquantes)
                ->description('Séances passées sans scan')
                ->descriptionIcon('heroicon-m-document-minus')
                ->color($feuillesManquantes > 0 ? 'warning' : 'success')
                ->url(SeanceResource::getUrl()),

            Stat::make('Signatures en attente', $signaturesEnAttente)
                ->description('Liens envoyés, non signés')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($signaturesEnAttente > 0 ? 'warning' : 'gray'),
        ];
    }
}
