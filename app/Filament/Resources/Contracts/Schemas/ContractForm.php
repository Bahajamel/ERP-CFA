<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ContractSignatureStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                            ->searchable(['nom', 'prenom'])
                            ->required(),
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'raison_sociale')
                            ->searchable()
                            ->required(),
                        Select::make('formation_id')
                            ->label('Formation')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload(),
                        Select::make('tuteur_id')
                            ->label('Tuteur')
                            ->relationship('tuteur', 'nom')
                            ->searchable(),
                    ]),
                Section::make('Détails du contrat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code_rncp')
                            ->label('Code RNCP')
                            ->placeholder('ex : RNCP34556'),
                        TextInput::make('rythme')
                            ->label("Rythme d'alternance")
                            ->placeholder('ex : 2 j CFA / 3 j entreprise'),
                        DatePicker::make('date_debut')
                            ->label('Date de début')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('lieu_formation')
                            ->label('Lieu de formation')
                            ->placeholder('ex : CFA de Lyon, 15 rue Garibaldi')
                            ->columnSpanFull(),
                    ]),
                Section::make('Signature & suivi')
                    ->description('Le statut du contrat évolue via les actions de workflow (Marquer signé, Faire évoluer), pas manuellement.')
                    ->columns(2)
                    ->schema([
                        Select::make('statut_signature')
                            ->label('Statut de signature')
                            ->options(ContractSignatureStatut::class)
                            ->default(ContractSignatureStatut::NonSigne->value)
                            ->required(),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
