<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Classe / promotion')
                    ->columns(2)
                    ->schema([
                        Select::make('formation_id')
                            ->label('Formation')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('libelle')
                            ->label('Libellé')
                            ->placeholder('Ex. 1ère année, Groupe A')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('annee_scolaire')
                            ->label('Année scolaire')
                            ->placeholder('Ex. 2025-2026')
                            ->maxLength(20),
                        DatePicker::make('date_debut')
                            ->label('Début')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_fin')
                            ->label('Fin')
                            ->displayFormat('d/m/Y'),
                    ]),
            ]);
    }
}
