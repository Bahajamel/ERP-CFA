<?php

namespace App\Filament\Resources\Opcos\Pages;

use App\Filament\Resources\Opcos\OpcoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOpco extends EditRecord
{
    protected static string $resource = OpcoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
