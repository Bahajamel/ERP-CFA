<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    /** Apprenants cochés dans le formulaire (champ virtuel, hors table promotions). */
    protected array $apprentisIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->apprentisIds = $data['apprentis_ids'] ?? [];
        unset($data['apprentis_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->composerApprentis($this->apprentisIds);
    }
}
