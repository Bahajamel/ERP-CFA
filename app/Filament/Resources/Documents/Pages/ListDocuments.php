<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    public function getSubheading(): ?string
    {
        return 'La GED unique du CFA : tous les documents (pièces candidat, contrats, CERFA, preuves '
            .'Qualiopi, livrables LivretRS…) rattachés à leur dossier. Classez par type et statut, '
            .'et taguez les livrables aux missions CFA qu\'ils prouvent.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
