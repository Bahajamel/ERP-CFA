<?php

namespace App\Filament\Resources\FinanceLines\Pages;

use App\Filament\Resources\FinanceLines\FinanceLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinanceLines extends ListRecords
{
    protected static string $resource = FinanceLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
