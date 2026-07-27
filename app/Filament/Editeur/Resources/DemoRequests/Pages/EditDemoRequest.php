<?php

namespace App\Filament\Editeur\Resources\DemoRequests\Pages;

use App\Filament\Editeur\Resources\DemoRequests\DemoRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDemoRequest extends EditRecord
{
    protected static string $resource = DemoRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DemoRequestResource::convertirAction(),
            // Une demande traitée peut être supprimée (nettoyage RGPD des prospects
            // qui n'ont pas donné suite).
            DeleteAction::make()->label('Supprimer la demande'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
