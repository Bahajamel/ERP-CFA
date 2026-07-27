<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    /** Apprenants cochés dans le formulaire (champ virtuel, hors table promotions). */
    protected array $apprentisIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Pré-coche les apprenants actuels de la classe. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['apprentis_ids'] = $this->getRecord()->apprentis()->pluck('candidates.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->apprentisIds = $data['apprentis_ids'] ?? [];
        unset($data['apprentis_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->composerApprentis($this->apprentisIds);
    }
}
