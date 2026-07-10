<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CfaMissions\CfaMissionResource;
use App\Models\CfaMission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Couverture des 14 missions CFA (L6231-2) par les livrables déposés dans la
 * GED. Vue de conformité : combien de missions disposent d'au moins une preuve.
 */
class CfaMissionsCouvertureWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Couverture des missions CFA (L6231-2)';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Aligné sur l'accès au registre (évite un lien mort vers une page interdite).
        return auth()->user()?->can('access_documents') ?? false;
    }

    protected function getStats(): array
    {
        $total = CfaMission::query()->count();
        $couvertes = CfaMission::query()->has('documents')->count();
        $nonCouvertes = $total - $couvertes;
        $taux = CfaMission::tauxCouverture();

        $couleurTaux = match (true) {
            $taux >= 90 => 'success',
            $taux >= 60 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Taux de couverture', $taux.' %')
                ->description($couvertes.' / '.$total.' missions avec au moins un livrable')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color($couleurTaux)
                ->url(CfaMissionResource::getUrl()),
            Stat::make('Missions couvertes', $couvertes)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(CfaMissionResource::getUrl()),
            Stat::make('Missions à couvrir', $nonCouvertes)
                ->description('Aucun livrable rattaché')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($nonCouvertes > 0 ? 'warning' : 'success')
                ->url(CfaMissionResource::getUrl()),
        ];
    }
}
