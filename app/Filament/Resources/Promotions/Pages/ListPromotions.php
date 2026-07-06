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
            .'séances et l\'assiduité, et c\'est l\'unité sur laquelle on valide le service fait mensuel.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
