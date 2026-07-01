<?php

namespace App\Filament\Resources\OpcoFiles\Pages;

use App\Filament\Resources\OpcoFiles\OpcoFileActions;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOpcoFile extends EditRecord
{
    protected static string $resource = OpcoFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpcoFileActions::preparerDepot(),
            OpcoFileActions::accepter(),
            OpcoFileActions::rejeter(),
            OpcoFileActions::changerStatut(),
            DeleteAction::make(),
        ];
    }
}
