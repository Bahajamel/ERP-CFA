<?php

namespace App\Filament\Resources\Matchings\Schemas;

use App\Enums\MatchingStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MatchingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposition candidat ↔ besoin')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            ->relationship('candidate', 'nom')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('need_id')
                            ->label('Besoin')
                            ->relationship('need', 'intitule_poste')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(MatchingStatut::class)
                            ->default(MatchingStatut::Propose->value)
                            ->required(),
                        Toggle::make('cv_envoye')
                            ->label('CV envoyé'),
                        DatePicker::make('date_entretien')
                            ->label("Date d'entretien")
                            ->displayFormat('d/m/Y'),
                        Textarea::make('retour_entreprise')
                            ->label('Retour entreprise')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
