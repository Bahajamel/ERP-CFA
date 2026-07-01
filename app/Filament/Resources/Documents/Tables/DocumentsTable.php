<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Document;
use App\Models\OpcoFile;
use App\Support\DocumentableTypes;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['documentable', 'uploadedBy']))
            ->columns([
                TextColumn::make('nom_fichier')
                    ->label('Libellé')
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn (Document $record) => $record->getFirstMedia('fichier')?->file_name),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('cible')
                    ->label('Dossier')
                    ->state(fn (Document $record) => self::cibleLabel($record)),
                TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => 'v'.$state)
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('uploadedBy.name')
                    ->label('Déposé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Déposé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type de document')
                    ->options(DocumentType::class),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(DocumentStatut::class),
                SelectFilter::make('documentable_type')
                    ->label('Type de dossier')
                    ->options(DocumentableTypes::options()),
                TernaryFilter::make('versions_courantes')
                    ->label('Versions')
                    ->placeholder('Versions courantes')
                    ->trueLabel('Versions courantes uniquement')
                    ->falseLabel('Toutes les versions')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->versionsCourantes(),
                        false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->versionsCourantes(),
                    ),
            ])
            ->recordActions([
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Document $record) => $record->getFirstMediaUrl('fichier'))
                    ->openUrlInNewTab()
                    ->visible(fn (Document $record) => $record->getFirstMedia('fichier') !== null),
                Action::make('remplacer')
                    ->label('Remplacer')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->schema([
                        FileUpload::make('fichier')
                            ->label('Nouveau fichier')
                            ->disk('public')
                            ->directory('documents-temp')
                            ->required(),
                        Select::make('statut')
                            ->label('Statut de la nouvelle version')
                            ->options(DocumentStatut::class)
                            ->default(DocumentStatut::Recu->value)
                            ->required(),
                    ])
                    ->action(function (Document $record, array $data) {
                        $nouvelle = $record->creerNouvelleVersion([
                            'statut' => $data['statut'],
                            'uploaded_by' => auth()->id(),
                        ]);

                        $nouvelle->addMediaFromDisk($data['fichier'], 'public')
                            ->toMediaCollection('fichier');

                        Notification::make()
                            ->title('Nouvelle version créée (v'.$nouvelle->version.')')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->label('Archiver'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Archiver'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function cibleLabel(Document $record): string
    {
        $typeLabel = DocumentableTypes::MAP[$record->documentable_type] ?? class_basename((string) $record->documentable_type);
        $target = $record->documentable;

        $nom = match (true) {
            $target instanceof Candidate => $target->nom_complet,
            $target instanceof Company => $target->raison_sociale,
            $target instanceof Contract => 'Contrat #'.$target->id,
            $target instanceof OpcoFile => 'Dossier OPCO #'.$target->id,
            default => '#'.$record->documentable_id,
        };

        return $typeLabel.' : '.$nom;
    }
}
