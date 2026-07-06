<?php

namespace App\Filament\Resources\Ruptures\RelationManagers;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Preuves du dossier de rupture (courrier de rupture, accusé, convention de
 * reclassement…) — rattachées via la GED polymorphe, sans duplication.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Preuves';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type de document')
                    ->options(DocumentType::class)
                    ->default(DocumentType::Autre->value)
                    ->required(),
                Select::make('statut')
                    ->label('Statut')
                    ->options(DocumentStatut::class)
                    ->default(DocumentStatut::Recu->value)
                    ->required(),
                TextInput::make('nom_fichier')
                    ->label('Libellé')
                    ->maxLength(255),
                SpatieMediaLibraryFileUpload::make('fichier')
                    ->label('Fichier')
                    ->collection('fichier')
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom_fichier')
            ->columns([
                TextColumn::make('nom_fichier')
                    ->label('Libellé')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->getFirstMedia('fichier')?->file_name),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Déposé le')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une preuve')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['uploaded_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn ($record) => $record->getFirstMediaUrl('fichier'))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->getFirstMedia('fichier') !== null),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
