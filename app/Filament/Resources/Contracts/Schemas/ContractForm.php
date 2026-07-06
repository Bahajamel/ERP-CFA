<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Candidate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parties au contrat')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Apprenti (candidat)')
                            ->relationship('candidate', 'nom')
                            ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->preload()
                            ->required(),
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'raison_sociale')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('formation_id')
                            ->label('Formation')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('tuteur_id')
                            ->label('Tuteur')
                            ->relationship('tuteur', 'nom')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                Section::make('Détails du contrat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code_rncp')
                            ->label('Code RNCP')
                            ->placeholder('ex : RNCP34556')
                            ->required(),
                        TextInput::make('rythme')
                            ->label("Rythme d'alternance")
                            ->placeholder('ex : 2 j CFA / 3 j entreprise')
                            ->required(),
                        DatePicker::make('date_debut')
                            ->label('Date de début')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('date_debut'),
                        TextInput::make('lieu_formation')
                            ->label('Lieu de formation')
                            ->placeholder('ex : CFA de Lyon, 15 rue Garibaldi')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('CERFA (contrat d\'apprentissage)')
                    ->description('Contrat d\'apprentissage entre le CFA et l\'entreprise (CERFA FA13). Déposez le document (PDF).')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cerfa')
                            ->label('Document CERFA')
                            ->collection('cerfa')
                            ->acceptedFileTypes(['application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
