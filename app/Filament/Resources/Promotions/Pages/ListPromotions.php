<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    public function getSubheading(): ?string
    {
        return 'Les classes / groupes d\'apprentis d\'une même formation et année. On y rattache les '
            .'séances et l\'assiduité — une classe correspond à une matière de l\'emploi du temps.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
