<?php

namespace App\Filament\Widgets;

use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\RuptureCaseResource;
use App\Models\RuptureCase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pilotage des ruptures : là où les concurrents s'arrêtent au constat, on suit
 * l'issue — combien de dossiers ouverts, combien d'apprentis reclassés vers un
 * nouvel employeur. Le taux de reclassement est un argument Qualiopi (critère 6).
 * Réservé à la Direction, la Pédagogie et l'Administrateur.
 */
class DossiersRuptureStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Ruptures & reclassement';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Pédagogie', 'Administratif', 'Administrateur']) ?? false;
    }

    protected function getStats(): array
    {
        $ouverts = RuptureCase::whereIn('statut', RuptureStatut::ouverts())->count();
        $enAccompagnement = RuptureCase::where('statut', RuptureStatut::EnAccompagnement->value)->count();
        $reclasses = RuptureCase::whereNotNull('nouvelle_company_id')->count();
        $total = RuptureCase::count();
        $tauxReclassement = $total > 0 ? round($reclasses / $total * 100) : 0;

        return [
            Stat::make('Dossiers de rupture ouverts', $ouverts)
                ->description('Non clos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($ouverts > 0 ? 'danger' : 'success')
                ->url(RuptureCaseResource::getUrl()),
            Stat::make('Accompagnements en cours', $enAccompagnement)
                ->description("Recherche d'un nouvel employeur")
                ->descriptionIcon('heroicon-m-lifebuoy')
                ->color('warning')
                ->url(RuptureCaseResource::getUrl()),
            Stat::make('Taux de reclassement', $tauxReclassement.' %')
                ->description($reclasses.' apprenti(s) reclassé(s) sur '.$total)
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color('success')
                ->url(RuptureCaseResource::getUrl()),
        ];
    }
}
