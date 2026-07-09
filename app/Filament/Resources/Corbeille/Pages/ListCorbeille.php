<?php

namespace App\Filament\Resources\Corbeille\Pages;

use App\Filament\Resources\Corbeille\CorbeilleResource;
use App\Models\Candidate;
use Filament\Resources\Pages\ListRecords;

class ListCorbeille extends ListRecords
{
    protected static string $resource = CorbeilleResource::class;

    public function getSubheading(): ?string
    {
        return 'Candidats supprimés, conservés '.Candidate::DELAI_PURGE_JOURS.' jours avant purge '
            .'définitive automatique. Restaurez un candidat pour le remettre (avec ses dossiers) dans les listes.';
    }
}
