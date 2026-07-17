<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Support\CustomFields;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomTable extends CreateRecord
{
    protected static string $resource = CustomTableResource::class;

    protected function getRedirectUrl(): string
    {
        // Après création : on ouvre l'édition pour saisir les lignes (onglet « Lignes »).
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /** Synchronise les colonnes définies dans le repeater vers le nouveau tableau. */
    protected function afterCreate(): void
    {
        CustomFields::synchroniserTableau($this->getRecord()->id, $this->data['colonnes'] ?? []);
    }
}
