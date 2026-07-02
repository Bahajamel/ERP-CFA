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
                            ->placeholder('ex : Dupont')
                            ->required(),
                        TextInput::make('prenom')
                            ->label('Prénom')
                            ->placeholder('ex : Marie')
                            ->required(),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->placeholder('ex : marie.dupont@email.com')
                            ->requiredWithout('telephone')
                            ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                        TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->placeholder('ex : 06 12 34 56 78')
                            ->requiredWithout('email')
                            ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                        DatePicker::make('date_naissance')
                            ->label('Date de naissance')
                            ->placeholder('ex : 15/03/2004')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('adresse')
                            ->label('Adresse')
                            ->placeholder('ex : 12 rue des Écoles, 75005 Paris')
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
                        Select::make('promotion_id')
                            ->label('Classe / Promotion')
                            ->relationship('promotion', 'libelle')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim($record->libelle.' — '.($record->annee_scolaire ?? '')))
                            ->searchable()
                            ->preload(),
                        TextInput::make('niveau_actuel')
                            ->label('Niveau actuel')
                            ->placeholder('ex : Terminale, Bac, Bac+2'),
                        TextInput::make('mobilite')
                            ->label('Mobilité')
                            ->placeholder('ex : Île-de-France, 30 km, permis B'),
                        TextInput::make('disponibilite')
                            ->label('Disponibilité')
                            ->placeholder('ex : Septembre 2026, immédiate'),
                        TextInput::make('source')
                            ->label('Source')
                            ->placeholder('ex : Salon, site web, LinkedIn, bouche-à-oreille'),
                        Select::make('commercial_id')
                            ->label('Commercial')
                            ->relationship('commercial', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(CandidateStatut::class)
                            ->default(CandidateStatut::Incomplet->value)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Le statut évolue via l\'action « Changer le statut » (transitions contrôlées).'),
                    ]),
            ]);
    }
}
