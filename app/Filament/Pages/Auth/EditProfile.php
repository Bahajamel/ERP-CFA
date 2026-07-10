<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * Page profil enrichie : ajoute le téléversement d'une photo de profil au-dessus
 * des champs standard (nom, email, mot de passe, double authentification).
 *
 * La photo est stockée sur le disque « public » (dossier `avatars/`) et exposée
 * ensuite partout par {@see \App\Models\User::getFilamentAvatarUrl()}.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getAvatarFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label('Photo de profil')
            ->helperText('JPG, PNG ou WebP — carré de préférence, 2 Mo maximum.')
            ->avatar()
            ->image()
            ->imageEditor()
            ->circleCropper()
            ->disk('public')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(2048)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
