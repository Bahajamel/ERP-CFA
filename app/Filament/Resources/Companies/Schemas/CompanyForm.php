<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyStatut;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Entreprise")
                    ->columns(2)
                    ->schema([
                        TextInput::make('raison_sociale')
                            ->label('Raison sociale')
                            ->required(),
                        TextInput::make('nom_commercial')
                            ->label('Nom commercial'),
                        TextInput::make('siret')
                            ->label('SIRET')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('secteur')
                            ->label("Secteur d'activité"),
                        TextInput::make('adresse')
                            ->label('Adresse')
                            ->columnSpanFull(),
                        Select::make('opco_id')
                            ->label('OPCO')
                            ->relationship('opco', 'nom')
                            ->searchable()
                            ->preload(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(CompanyStatut::class)
                            ->default(CompanyStatut::Prospect->value)
                            ->required(),
                    ]),
            ]);
    }
}
