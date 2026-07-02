<?php

namespace App\Filament\Resources\FinanceLines\Pages;

use App\Filament\Resources\FinanceLines\FinanceLineResource;
use App\Filament\Widgets\FinanceEncaissementChart;
use App\Filament\Widgets\FinanceFacturesChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinanceLines extends ListRecords
{
    protected static string $resource = FinanceLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceEncaissementChart::class,
            FinanceFacturesChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
