<?php

namespace App\Filament\Resources\Seances\Schemas;

use App\Enums\SeanceStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class SeanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('promotion_id')
                    ->label('Classe / Promotion')
                    ->relationship('promotion', 'libelle')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Les apprentis de la classe seront émargés automatiquement.'),
                TextInput::make('libelle')
                    ->label('Intitulé / matière')
                    ->maxLength(255),
                DatePicker::make('date')
                    ->label('Date')
                    ->default(now())
                    ->displayFormat('d/m/Y')
                    ->required(),
                TimePicker::make('heure_debut')
                    ->label('Heure de début')
                    ->seconds(false),
                TimePicker::make('heure_fin')
                    ->label('Heure de fin')
                    ->seconds(false),
                Select::make('formateur_id')
                    ->label('Formateur')
                    ->relationship('formateur', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class)
                    ->default(SeanceStatut::Planifiee->value)
                    ->required(),
            ]);
    }
}
