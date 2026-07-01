<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
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
            ]);
    }
}
