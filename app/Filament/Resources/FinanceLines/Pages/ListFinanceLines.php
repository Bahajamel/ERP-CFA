<?php

namespace App\Filament\Resources\FinanceLines\Pages;

use App\Filament\Exports\FinanceLineExporter;
use App\Filament\Resources\FinanceLines\FinanceLineResource;
use App\Filament\Widgets\FinanceEncaissementChart;
use App\Filament\Widgets\FinanceFacturesChart;
use App\Filament\Widgets\FinanceRecouvrementStats;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListFinanceLines extends ListRecords
{
    protected static string $resource = FinanceLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(FinanceLineExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceRecouvrementStats::class,
            FinanceEncaissementChart::class,
            FinanceFacturesChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
