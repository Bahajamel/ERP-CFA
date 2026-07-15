<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Organisation;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Rattache le compte créé au CFA courant. Indispensable : sans appartenance,
     * l'utilisateur passe canAccessPanel() mais getTenants() lui renvoie une liste
     * vide — il se connecte et n'a accès à aucun CFA. C'est aussi ce rattachement
     * qui le rend visible dans la liste (voir UserResource::getEloquentQuery()).
     */
    protected function afterCreate(): void
    {
        if (($tenant = Filament::getTenant()) instanceof Organisation) {
            $this->record->organisations()->syncWithoutDetaching($tenant);
        }
    }
}
