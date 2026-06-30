<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Enums\CandidateStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom')
                            ->required(),
                        TextInput::make('prenom')
                            ->label('Prénom')
                            ->required(),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email(),
                        TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel(),
                        DatePicker::make('date_naissance')
                            ->label('Date de naissance')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('adresse')
                            ->label('Adresse')
                            ->columnSpanFull(),
                    ]),
                Section::make('Formation & suivi')
                    ->columns(2)
                    ->schema([
                        Select::make('formation_visee_id')
                            ->label('Formation visée')
                            ->relationship('formationVisee', 'libelle')
                            ->searchable()
                            ->preload(),
                        TextInput::make('niveau_actuel')
                            ->label('Niveau actuel'),
                        TextInput::make('mobilite')
                            ->label('Mobilité'),
                        TextInput::make('disponibilite')
                            ->label('Disponibilité'),
                        TextInput::make('source')
                            ->label('Source'),
                        Select::make('commercial_id')
                            ->label('Commercial')
                            ->relationship('commercial', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(CandidateStatut::class)
                            ->default(CandidateStatut::Incomplet->value)
                            ->required(),
                    ]),
            ]);
    }
}
