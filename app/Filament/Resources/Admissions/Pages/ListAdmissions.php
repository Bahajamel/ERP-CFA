<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Widgets\ConversionFunnelChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdmissions extends ListRecords
{
    protected static string $resource = AdmissionResource::class;

    public function getSubheading(): ?string
    {
        return 'La vérification du dossier avant contractualisation : cochez les pièces obligatoires, '
            .'contrôlez leur conformité, puis validez ou refusez l\'admission. Un dossier incomplet '
            .'bloque le passage à l\'étape suivante — la protection du financement commence ici.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ConversionFunnelChart::class,
        ];
    }
}
