<?php

namespace App\Filament\Resources\OpcoFiles\Pages;

use App\Filament\Exports\OpcoFileExporter;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListOpcoFiles extends ListRecords
{
    protected static string $resource = OpcoFileResource::class;

    public function getSubheading(): ?string
    {
        return 'Le nerf de la guerre : le financement de chaque contrat par l\'OPCO. Déposez le dossier, '
            .'suivez son statut (déposé → accepté), et traitez sans délai les rejets / demandes de '
            .'correction — un dossier bloqué, c\'est du financement en danger.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(OpcoFileExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }
}
