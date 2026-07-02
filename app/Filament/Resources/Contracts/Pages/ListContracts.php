<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Widgets\ContratsSignesParMoisChart;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(ContractExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContratsSignesParMoisChart::class,
        ];
    }
}
