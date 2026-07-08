<?php

namespace App\Filament\Resources\Entretiens\Pages;

use App\Filament\Resources\Entretiens\EntretienResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEntretiens extends ListRecords
{
    protected static string $resource = EntretienResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendrier')
                ->label('Vue calendrier')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(EntretienResource::getUrl('calendrier')),
            CreateAction::make()->label('Planifier un entretien'),
        ];
    }
}
