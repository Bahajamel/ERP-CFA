<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    public function getSubheading(): ?string
    {
        return 'Le contrat d\'apprentissage, de la préparation à l\'archivage. Faites-le évoluer via les '
            .'actions (signature électronique, transmission OPCO, activation). En cas de problème, '
            .'ouvrez un dossier de rupture directement depuis le contrat.';
    }

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
}
