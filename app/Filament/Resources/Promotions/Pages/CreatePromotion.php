<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    /** Apprenants cochés dans le formulaire (champ virtuel, hors table promotions). */
    protected array $apprentisIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Une classe = une cohorte : une seule « 1ère année » par formation et année scolaire.
        $existe = Promotion::where('formation_id', $data['formation_id'] ?? null)
            ->where('libelle', $data['libelle'] ?? null)
            ->where('annee_scolaire', $data['annee_scolaire'] ?? null)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'data.formation_annee' => 'Cette classe existe déjà pour cette année scolaire — modifiez-la plutôt.',
            ]);
        }

        $this->apprentisIds = $data['apprentis_ids'] ?? [];
        unset($data['apprentis_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->composerApprentis($this->apprentisIds);
    }
}
