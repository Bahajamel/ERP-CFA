<?php

namespace App\Filament\Resources\FaqBots\Pages;

use App\Filament\Resources\FaqBots\FaqBotResource;
use Filament\Resources\Pages\ListRecords;

class ListFaqBots extends ListRecords
{
    protected static string $resource = FaqBotResource::class;

    public function getSubheading(): ?string
    {
        return 'Un assistant par grande partie du logiciel. Il apparaît automatiquement sur les pages de sa section.';
    }
}
