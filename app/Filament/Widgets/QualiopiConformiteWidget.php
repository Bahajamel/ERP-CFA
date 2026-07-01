<?php

namespace App\Filament\Widgets;

use App\Enums\QualiopiStatut;
use App\Filament\Resources\QualiopiIndicators\QualiopiIndicatorResource;
use App\Models\QualiopiIndicator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Baromètre de conformité Qualiopi : audit-readiness en un coup d'œil.
 * Réservé à la Direction, la Qualité et l'Administrateur.
 */
class QualiopiConformiteWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Conformité Qualiopi';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Qualité', 'Administrateur']) ?? false;
    }

    protected function getStats(): array
    {
        $taux = QualiopiIndicator::tauxConformite();
        $conformes = QualiopiIndicator::where('statut', QualiopiStatut::Conforme->value)->count();
        $nonConformes = QualiopiIndicator::where('statut', QualiopiStatut::NonConforme->value)->count();
        $aVerifier = QualiopiIndicator::where('statut', QualiopiStatut::AVerifier->value)->count();

        $couleurTaux = match (true) {
            $taux >= 90 => 'success',
            $taux >= 70 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Taux de conformité', $taux.' %')
                ->description('Indicateurs conformes (hors non applicables)')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($couleurTaux)
                ->url(QualiopiIndicatorResource::getUrl()),
            Stat::make('Conformes', $conformes)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(QualiopiIndicatorResource::getUrl()),
            Stat::make('Non conformes', $nonConformes)
                ->description('À corriger avant l\'audit')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($nonConformes > 0 ? 'danger' : 'success')
                ->url(QualiopiIndicatorResource::getUrl()),
            Stat::make('À vérifier', $aVerifier)
                ->description('Non encore statués')
                ->descriptionIcon('heroicon-m-clock')
                ->color($aVerifier > 0 ? 'warning' : 'success')
                ->url(QualiopiIndicatorResource::getUrl()),
        ];
    }
}
