<?php

namespace App\Filament\Resources\QualiopiIndicators\Pages;

use App\Filament\Resources\QualiopiIndicators\QualiopiIndicatorResource;
use App\Filament\Widgets\QualiopiConformiteWidget;
use Filament\Resources\Pages\ListRecords;

class ListQualiopiIndicators extends ListRecords
{
    protected static string $resource = QualiopiIndicatorResource::class;

    public function getSubheading(): ?string
    {
        return 'Qualiopi est la certification qualité obligatoire pour tout CFA financé sur '
            .'fonds publics. Ce registre suit les 7 critères et 32 indicateurs du Référentiel '
            .'National Qualité : pour chacun, indiquez s\'il est conforme, qui en est responsable, '
            .'et joignez les preuves. Objectif : être prêt pour l\'audit à tout moment. Le badge '
            .'rouge dans le menu = nombre d\'indicateurs « non conformes » à corriger.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QualiopiConformiteWidget::class,
        ];
    }

    // Pas d'action de création : les 32 indicateurs proviennent du référentiel RNQ.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
