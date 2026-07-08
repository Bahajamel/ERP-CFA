<?php

namespace App\Filament\Resources\Entretiens\Pages;

use App\Filament\Resources\Entretiens\EntretienActions;
use App\Filament\Resources\Entretiens\EntretienResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEntretien extends EditRecord
{
    protected static string $resource = EntretienResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EntretienActions::planifier(),
            EntretienActions::reprogrammer(),
            EntretienActions::marquerRealise(),
            EntretienActions::accepterCandidat(),
            EntretienActions::refuserCandidat(),
            EntretienActions::marquerAbsent(),
            EntretienActions::annuler(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
