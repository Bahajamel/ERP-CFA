<?php

namespace App\Filament\Resources\Admissions\RelationManagers;

use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use App\Models\AdmissionChecklistItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Pièces obligatoires';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type')
                    ->label('Type de pièce')
                    ->options(DocumentType::optionsPour(DocumentType::pourAdmission()))
                    ->required(),
                Select::make('statut')
                    ->label('Statut')
                    ->options(ChecklistItemStatut::class)
                    ->default(ChecklistItemStatut::Manquante->value)
                    ->required(),
                Toggle::make('est_obligatoire')
                    ->label('Obligatoire')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type')
            ->columns([
                TextColumn::make('document_type')
                    ->label('Pièce')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('est_obligatoire')
                    ->label('Obligatoire')
                    ->boolean(),
                IconColumn::make('document_id')
                    ->label('Fichier')
                    ->boolean()
                    ->tooltip(fn (AdmissionChecklistItem $record) => $record->document?->nom_fichier
                        ?? $record->document?->getFirstMedia('fichier')?->file_name),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Ajouter une pièce'),
            ])
            ->recordActions([
                self::joindreFichier(),
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (AdmissionChecklistItem $record) => $record->document?->getFirstMediaUrl('fichier'))
                    ->openUrlInNewTab()
                    ->visible(fn (AdmissionChecklistItem $record) => $record->document?->getFirstMedia('fichier') !== null),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Dépose le fichier d'une pièce directement depuis l'admission : crée (ou met
     * à jour) le document dans la GED, rattaché au candidat, le lie à la pièce, et
     * passe automatiquement son statut à « présente ». Un seul geste.
     */
    protected static function joindreFichier(): Action
    {
        return Action::make('joindreFichier')
            ->label(fn (AdmissionChecklistItem $record) => $record->document_id ? 'Remplacer le fichier' : 'Joindre le fichier')
            ->icon(Heroicon::OutlinedPaperClip)
            ->modalHeading('Déposer la pièce')
            ->modalSubmitActionLabel('Enregistrer')
            ->schema([
                FileUpload::make('fichier')
                    ->label('Fichier de la pièce')
                    ->required()
                    ->storeFiles(false)
                    ->downloadable(),
            ])
            ->action(function (AdmissionChecklistItem $record, array $data): void {
                $file = Arr::wrap($data['fichier']);
                $file = reset($file);

                if ($file === false) {
                    return;
                }

                try {
                    $record->attacherPreuve(
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        auth()->id(),
                    );
                } catch (\RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Pièce déposée')
                    ->body('Le fichier est enregistré dans la GED et la pièce passe à « présente ».')
                    ->send();
            });
    }
}
