<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRupture extends EditRecord
{
    protected static string $resource = RuptureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
