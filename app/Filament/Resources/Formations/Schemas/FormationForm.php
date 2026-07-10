<?php

namespace App\Filament\Resources\Formations\Schemas;

use Filament\Forms\Components\TagsInput;
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
                    ->placeholder('ex : CAP Boulanger, BTS SIO')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('code_rncp')
                    ->label('Code RNCP')
                    ->placeholder('ex : RNCP34556'),
                TextInput::make('niveau')
                    ->label('Niveau')
                    ->placeholder('ex : 3 (CAP), 5 (BTS), 6 (Licence)'),
                TextInput::make('duree_mois')
                    ->label('Durée (mois)')
                    ->placeholder('ex : 24')
                    ->numeric(),
                TextInput::make('rythme_defaut')
                    ->label('Rythme par défaut')
                    ->placeholder('ex : 2 j CFA / 3 j entreprise'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TagsInput::make('matieres')
                    ->label('Matières (programme)')
                    ->placeholder('Ajouter une matière…')
                    ->helperText('Le catalogue des matières enseignées dans cette formation. Elles seront proposées comme matières de séances.')
                    ->columnSpanFull(),
            ]);
    }
}
