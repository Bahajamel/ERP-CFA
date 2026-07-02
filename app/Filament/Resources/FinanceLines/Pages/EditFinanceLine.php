<?php

namespace App\Filament\Resources\FinanceLines\Pages;

use App\Filament\Resources\FinanceLines\FinanceLineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinanceLine extends EditRecord
{
    protected static string $resource = FinanceLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
