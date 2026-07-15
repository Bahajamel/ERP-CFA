<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

/**
 * Fiche du CFA courant, éditable depuis le panneau CFA (menu du sélecteur).
 * Portée volontairement réduite au nom : l'identifiant d'URL (slug) et l'état
 * actif/suspendu relèvent de l'éditeur (panneau /editeur) — un CFA ne peut ni
 * se réactiver lui-même ni casser ses propres adresses.
 */
class ProfilCfa extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Fiche du CFA';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom du CFA')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Apparaît dans le sélecteur de CFA et sur vos documents.'),
            ]);
    }
}
