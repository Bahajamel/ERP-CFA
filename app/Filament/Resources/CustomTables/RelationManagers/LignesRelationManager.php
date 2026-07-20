<?php

namespace App\Filament\Resources\CustomTables\RelationManagers;

use App\Models\CustomRecord;
use App\Models\CustomView;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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

        // Colonnes dynamiques + recherche globale sur le JSONB : on rend la 1re
        // colonne « searchable » avec une requête portable (CAST … LIKE) qui
        // balaie toute la ligne — la barre de recherche s'affiche ainsi.
        $colonnesDynamiques = CustomFields::colonnes($colonnes, 'data');
        if ($colonnesDynamiques !== []) {
            $colonnesDynamiques[0]->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('CAST(data AS TEXT) LIKE ?', ['%'.$search.'%']));
        }

        return $table
            ->columns([
                ...$colonnesDynamiques,
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderableColumns()
            ->filters(CustomFields::filtres($colonnes, 'data'))
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->visible(fn (): bool => $this->getOwnerRecord()->colonnes->isNotEmpty()
                        && (Auth::user()?->can('create', CustomRecord::class) ?? false)),
                $this->vuesAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (CustomRecord $record): bool => Auth::user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->visible(fn (CustomRecord $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->can('custom_records.delete') ?? false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-plus-circle')
            ->emptyStateHeading('Aucune ligne')
            ->emptyStateDescription('Ajoutez votre première ligne à ce tableau.');
    }

    /**
     * Vues enregistrées (basiques) : préréglages nommés de filtres + tri, propres
     * au CFA. On applique un préréglage d'un clic ou on enregistre l'état courant.
     * Réservé aux profils qui peuvent configurer le tableau (custom_tables.update).
     */
    protected function vuesAction(): ActionGroup
    {
        $vues = $this->getOwnerRecord()->views()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $actions = $vues->map(fn (CustomView $vue): Action => Action::make('vue_'.$vue->getKey())
            ->label($vue->is_default ? $vue->name.' • défaut' : $vue->name)
            ->icon('heroicon-o-eye')
            ->action(function () use ($vue): void {
                $this->tableSort = data_get($vue->sort, 'tableSort');
                $this->tableFilters = $vue->filters ?? [];
                $this->resetTable();
            }))->all();

        $actions[] = Action::make('enregistrerVue')
            ->label('Enregistrer la vue actuelle')
            ->icon('heroicon-o-bookmark')
            ->schema([
                TextInput::make('name')
                    ->label('Nom de la vue')
                    ->placeholder('ex : Partenaires actifs')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_default')
                    ->label('Définir comme vue par défaut'),
            ])
            ->action(function (array $data): void {
                CustomView::create([
                    'custom_table_id' => $this->getOwnerRecord()->getKey(),
                    'name' => $data['name'],
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'sort' => ['tableSort' => $this->tableSort],
                    'filters' => $this->tableFilters ?? [],
                ]);

                Notification::make()->success()->title('Vue enregistrée')->send();
            });

        return ActionGroup::make($actions)
            ->label('Vues')
            ->icon('heroicon-o-eye')
            ->button()
            ->color('gray')
            ->visible(fn (): bool => Auth::user()?->can('custom_tables.update') ?? false);
    }
}
