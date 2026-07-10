<?php

namespace App\Filament\Resources\Matchings\Pages;

use App\Filament\Resources\Matchings\MatchingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMatching extends EditRecord
{
    protected static string $resource = MatchingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
