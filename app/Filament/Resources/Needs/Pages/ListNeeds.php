<?php

namespace App\Filament\Resources\Needs\Pages;

use App\Filament\Exports\NeedExporter;
use App\Filament\Resources\Needs\NeedResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListNeeds extends ListRecords
{
    protected static string $resource = NeedResource::class;

    public function getSubheading(): ?string
    {
        return 'Un besoin = un poste à pourvoir chez une entreprise (métier, formation visée, rythme). '
            .'C\'est ce que le matching cherche à combler avec vos candidats. Suivez chaque besoin de '
            .'sa création jusqu\'au candidat retenu via la « Vue Pipeline ».';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(NeedResource::getUrl('kanban')),
            CreateAction::make(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(NeedExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }
}
