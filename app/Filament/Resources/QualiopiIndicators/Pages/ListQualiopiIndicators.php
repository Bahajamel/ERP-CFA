<?php

namespace App\Filament\Resources\QualiopiIndicators\Pages;

use App\Filament\Resources\QualiopiIndicators\QualiopiIndicatorResource;
use Filament\Resources\Pages\ListRecords;

class ListQualiopiIndicators extends ListRecords
{
    protected static string $resource = QualiopiIndicatorResource::class;

    // Pas d'action de création : les 32 indicateurs proviennent du référentiel RNQ.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
