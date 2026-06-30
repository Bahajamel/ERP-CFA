<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom complet')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Adresse e-mail')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    // Haché automatiquement via le cast 'password' => 'hashed'
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Laisser vide pour conserver le mot de passe actuel.')
                    ->maxLength(255),

                Select::make('roles')
                    ->label('Rôles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required()
                    ->helperText('Détermine les modules accessibles par l\'utilisateur.'),

                Toggle::make('is_active')
                    ->label('Compte actif')
                    ->helperText('Un compte désactivé ne peut plus se connecter (l\'historique est conservé).')
                    ->default(true),
            ]);
    }
}
