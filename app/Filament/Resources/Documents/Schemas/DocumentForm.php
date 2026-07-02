<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\CfaMission;
use App\Support\DocumentableTypes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rattachement')
                    ->description('À quel dossier ce document appartient-il ?')
                    ->columns(2)
                    ->schema([
                        Select::make('documentable_type')
                            ->label('Type de dossier')
                            ->options(DocumentableTypes::options())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('documentable_id', null))
                            ->required(),
                        Select::make('documentable_id')
                            ->label('Dossier concerné')
                            ->options(fn (Get $get) => DocumentableTypes::records($get('documentable_type')))
                            ->searchable()
                            ->required(),
                    ]),
                Section::make('Document')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type de document')
                            ->options(DocumentType::class)
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(DocumentStatut::class)
                            ->default(DocumentStatut::EnAttente->value)
                            ->required(),
                        TextInput::make('nom_fichier')
                            ->label('Libellé')
                            ->placeholder('Ex. Contrat signé 2026')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('fichier')
                            ->label('Fichier')
                            ->collection('fichier')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Missions CFA couvertes')
                    ->description('Missions du CFA (article L6231-2) que ce document permet de prouver. Un livrable peut en couvrir plusieurs.')
                    ->schema([
                        Select::make('missions')
                            ->label('Missions L6231-2')
                            ->relationship('missions', 'titre')
                            ->getOptionLabelFromRecordUsing(fn (CfaMission $record) => $record->numero.'° '.$record->titre)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder('Ex. 4° Information sur les droits et devoirs')
                            ->columnSpanFull(),
                        Select::make('source')
                            ->label('Origine')
                            ->options(DocumentSource::class)
                            ->default(DocumentSource::Manuel->value)
                            ->required(),
                    ]),
            ]);
    }
}
