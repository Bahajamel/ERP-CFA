<?php

namespace App\Filament\Resources\Formations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('libelle')
                    ->label('Libellé')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('code_rncp')
                    ->label('Code RNCP'),
                TextInput::make('niveau')
                    ->label('Niveau'),
                TextInput::make('duree_mois')
                    ->label('Durée (mois)')
                    ->numeric(),
                TextInput::make('rythme_defaut')
                    ->label('Rythme par défaut'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
