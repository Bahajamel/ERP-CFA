<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Support\CustomFields;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomTable extends EditRecord
{
    protected static string $resource = CustomTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Préremplit le repeater des colonnes avec celles déjà définies. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['colonnes'] = CustomFields::lignesDepuis($this->getRecord()->colonnes);

        return $data;
    }

    /** Répercute les changements de colonnes (ajout / renommage / suppression). */
    protected function afterSave(): void
    {
        CustomFields::synchroniserTableau($this->getRecord()->id, $this->data['colonnes'] ?? []);
    }
}
