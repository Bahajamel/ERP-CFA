<?php

namespace App\Filament\Resources\OpcoFiles\Pages;

use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOpcoFile extends EditRecord
{
    protected static string $resource = OpcoFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
