<?php

namespace App\Filament\Resources\FinanceLines\Pages;

use App\Filament\Resources\FinanceLines\FinanceLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinanceLine extends CreateRecord
{
    protected static string $resource = FinanceLineResource::class;
}
