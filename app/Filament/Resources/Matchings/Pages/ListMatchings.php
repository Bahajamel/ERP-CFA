<?php

namespace App\Filament\Resources\Matchings\Pages;

use App\Filament\Resources\Matchings\MatchingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatchings extends ListRecords
{
    protected static string $resource = MatchingResource::class;

    public function getSubheading(): ?string
    {
        return 'Le rapprochement candidat ↔ besoin d\'entreprise. Proposez un candidat sur un poste, '
            .'suivez l\'entretien et l\'issue. Un matching accepté ouvre la voie à l\'admission puis '
            .'au contrat d\'apprentissage.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
