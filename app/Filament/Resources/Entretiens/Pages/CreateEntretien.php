<?php

namespace App\Filament\Resources\Entretiens\Pages;

use App\Filament\Resources\Entretiens\EntretienResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEntretien extends CreateRecord
{
    protected static string $resource = EntretienResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
