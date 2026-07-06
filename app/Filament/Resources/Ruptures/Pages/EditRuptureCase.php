<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureCaseActions;
use App\Filament\Resources\Ruptures\RuptureCaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRuptureCase extends EditRecord
{
    protected static string $resource = RuptureCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RuptureCaseActions::changerStatut(),
            RuptureCaseActions::reclasser(),
            RuptureCaseActions::clore(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
