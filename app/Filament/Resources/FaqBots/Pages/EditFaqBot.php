<?php

namespace App\Filament\Resources\FaqBots\Pages;

use App\Filament\Resources\FaqBots\FaqBotResource;
use Filament\Resources\Pages\EditRecord;

class EditFaqBot extends EditRecord
{
    protected static string $resource = FaqBotResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
