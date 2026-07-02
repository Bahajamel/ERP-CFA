<?php

namespace App\Filament\Resources\Needs\Schemas;

use App\Enums\NeedStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Poste recherché')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'raison_sociale')
                            ->searchable()
                            ->required(),
                        TextInput::make('intitule_poste')
                            ->label('Intitulé du poste')
                            ->placeholder('ex : Apprenti boulanger, Développeur web')
                            ->required(),
                        Select::make('formation_id')
                            ->label('Formation visée')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload(),
                        TextInput::make('localisation')
                            ->label('Localisation')
                            ->placeholder('ex : Lyon 3e, télétravail partiel'),
                        DatePicker::make('date_demarrage')
                            ->label('Date de démarrage souhaitée')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('nb_postes')
                            ->label('Nombre de postes')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('rythme')
                            ->label("Rythme d'alternance")
                            ->placeholder('ex : 2 j CFA / 3 j entreprise'),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(NeedStatut::class)
                            ->default(NeedStatut::Cree->value)
                            ->required(),
                        Textarea::make('prerequis')
                            ->label('Prérequis')
                            ->placeholder('ex : Niveau CAP, permis B, expérience en vente appréciée')
                            ->columnSpanFull(),
                    ]),
                Section::make('Interlocuteurs entreprise')
                    ->columns(2)
                    ->schema([
                        Select::make('contact_id')
                            ->label('Contact responsable')
                            ->relationship('contact', 'nom')
                            ->searchable(),
                        Select::make('tuteur_id')
                            ->label('Tuteur prévu')
                            ->relationship('tuteur', 'nom')
                            ->searchable(),
                    ]),
            ]);
    }
}
