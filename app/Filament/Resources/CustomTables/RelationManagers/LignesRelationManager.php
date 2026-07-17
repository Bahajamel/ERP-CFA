<?php

namespace App\Filament\Resources\CustomTables\RelationManagers;

use App\Support\CustomFields;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lignes d'un tableau personnalisé : formulaire et colonnes construits
 * dynamiquement à partir des colonnes définies pour ce tableau (valeurs stockées
 * dans custom_records.data). C'est la partie « saisie » façon Monday.
 */
class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'records';

    protected static ?string $title = 'Lignes';

    protected static ?string $modelLabel = 'ligne';

    protected static ?string $pluralModelLabel = 'lignes';

    public function form(Schema $schema): Schema
    {
        $colonnes = $this->getOwnerRecord()->colonnes;

        if ($colonnes->isEmpty()) {
            return $schema->components([
                Placeholder::make('aucune_colonne')
                    ->hiddenLabel()
                    ->content('Définissez d\'abord des colonnes (section « Colonnes » ci-dessus) pour pouvoir saisir des lignes.'),
            ]);
        }

        return $schema->columns(2)->components(CustomFields::champs($colonnes, 'data'));
    }

    public function table(Table $table): Table
    {
        $colonnes = $this->getOwnerRecord()->colonnes;

        return $table
            ->columns([
                ...CustomFields::colonnes($colonnes, 'data'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->visible(fn (): bool => $this->getOwnerRecord()->colonnes->isNotEmpty()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-plus-circle')
            ->emptyStateHeading('Aucune ligne')
            ->emptyStateDescription('Ajoutez votre première ligne à ce tableau.');
    }
}
