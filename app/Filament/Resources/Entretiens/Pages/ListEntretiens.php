<?php

namespace App\Filament\Resources\Entretiens\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Entretiens\EntretienResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEntretiens extends ListRecords
{
    protected static string $resource = EntretienResource::class;

    /**
     * Aucune action de création ici : un entretien se planifie depuis la fiche
     * candidat. On renvoie vers les Candidats et vers la vue calendrier.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendrier')
                ->label('Vue calendrier')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(EntretienResource::getUrl('calendrier')),
            Action::make('candidats')
                ->label('Planifier depuis un candidat')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('primary')
                ->url(CandidateResource::getUrl('index')),
        ];
    }
}
