<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Enums\CustomFieldType;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Models\CustomFieldDefinition;
use App\Models\CustomRecord;
use App\Models\CustomTable;
use App\Models\CustomView;
use App\Support\BoardNavigation;
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
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Page « board » d'un tableau personnalisé : affiche DIRECTEMENT les lignes (façon
 * Monday) quand on ouvre un tableau — la configuration des colonnes reste
 * accessible via le bouton « Configurer les colonnes » (page d'édition).
 */
class BoardCustomTable extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = CustomTableResource::class;

    protected string $view = 'filament.resources.custom-tables.pages.board';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Auth::user()?->can('view', $this->record) ?? false, 403);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->description;
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            // Vue Kanban : proposée seulement si le tableau a une colonne « Statut »
            // (ou une liste) sur laquelle regrouper les cartes.
            Action::make('kanban')
                ->label('Vue Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('gray')
                ->url(fn (): string => CustomTableResource::getUrl('kanban', ['record' => $record]))
                ->visible(fn (): bool => $this->colonneStatut() !== null),

            // Lien de candidature PROPRE à ce tableau : le formulaire public crée
            // une ligne dans CE tableau.
            Action::make('lienCandidature')
                ->label('Lien de candidature')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->can('update', $record) ?? false)
                ->modalHeading('Lien de candidature de ce tableau')
                ->modalDescription('Partagez ce lien : la personne remplit le formulaire et une ligne est créée dans ce tableau, sans accès à l\'ERP.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->schema([
                    Placeholder::make('lien')
                        ->hiddenLabel()
                        ->content(fn () => view('filament.candidature-lien', ['lien' => $record->lienCandidature()])),
                ]),

            // Ajouter / gérer les colonnes directement depuis le board.
            CustomFields::gererActionTableau($record->getKey(), 'gererColonnes')
                ->visible(fn (): bool => Auth::user()?->can('update', $record) ?? false),

            Action::make('configurer')
                ->label('Configurer')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn (): string => CustomTableResource::getUrl('edit', ['record' => $record]))
                ->visible(fn (): bool => Auth::user()?->can('update', $record) ?? false),

            // Supprimer le tableau (corbeille) directement depuis le board.
            Action::make('supprimer')
                ->label('Supprimer le tableau')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Auth::user()?->can('delete', $record) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Supprimer ce tableau ?')
                ->modalDescription('Le tableau « '.$record->name.' » et ses lignes seront placés en corbeille : '
                    .'il disparaît de la liste et du sélecteur de tables. Cette action est réversible par un administrateur.')
                ->modalSubmitActionLabel('Supprimer le tableau')
                ->action(function () use ($record) {
                    $record->delete();

                    Notification::make()->success()->title('Tableau supprimé')->send();

                    return redirect($this->urlRetour($record));
                }),
        ];
    }

    /** Après suppression : retour au module d'origine, sinon à la liste Administration. */
    private function urlRetour(CustomTable $record): string
    {
        $contextes = BoardNavigation::contextes();

        return isset($contextes[$record->context])
            ? $contextes[$record->context]['resource']::getUrl('index')
            : CustomTableResource::getUrl('index');
    }

    /**
     * Colonne sur laquelle bâtir le Kanban : la première de type « Statut », sinon
     * la première « Liste déroulante ». null si aucune → pas de vue Kanban.
     */
    public function colonneStatut(): ?CustomFieldDefinition
    {
        $colonnes = $this->getRecord()->colonnes;

        return $colonnes->first(fn (CustomFieldDefinition $c): bool => $c->type === CustomFieldType::Statut)
            ?? $colonnes->first(fn (CustomFieldDefinition $c): bool => $c->type === CustomFieldType::Select);
    }

    public function table(Table $table): Table
    {
        $record = $this->getRecord();
        $colonnes = $record->colonnes;

        // Colonnes dynamiques + recherche globale sur le JSONB (portable).
        $colonnesDynamiques = CustomFields::colonnes($colonnes, 'data');
        if ($colonnesDynamiques !== []) {
            $colonnesDynamiques[0]->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('CAST(data AS TEXT) LIKE ?', ['%'.$search.'%']));
        }

        return $table
            ->query(fn (): Builder => CustomRecord::query()->where('custom_table_id', $record->getKey()))
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
                    ->visible(fn (): bool => $colonnes->isNotEmpty()
                        && (Auth::user()?->can('create', CustomRecord::class) ?? false))
                    ->schema(CustomFields::champs($colonnes, 'data'))
                    ->using(fn (array $data): CustomRecord => CustomRecord::create(
                        $data + ['custom_table_id' => $record->getKey()],
                    )),
                $this->vuesAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(CustomFields::champs($colonnes, 'data'))
                    ->visible(fn (CustomRecord $ligne): bool => Auth::user()?->can('update', $ligne) ?? false),
                DeleteAction::make()
                    ->visible(fn (CustomRecord $ligne): bool => Auth::user()?->can('delete', $ligne) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()?->can('custom_records.delete') ?? false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-plus-circle')
            ->emptyStateHeading($colonnes->isEmpty() ? 'Définissez d\'abord des colonnes' : 'Aucune ligne')
            ->emptyStateDescription($colonnes->isEmpty()
                ? 'Cliquez sur « Configurer les colonnes » pour définir la structure de ce tableau, puis revenez ajouter des lignes.'
                : 'Ajoutez votre première ligne à ce tableau.');
    }

    /**
     * Vues enregistrées (préréglages filtres + tri nommés), propres au CFA.
     * Réservé aux profils qui peuvent configurer le tableau (custom_tables.update).
     */
    protected function vuesAction(): ActionGroup
    {
        $record = $this->getRecord();

        $actions = $record->views()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomView $vue): Action => Action::make('vue_'.$vue->getKey())
                ->label($vue->is_default ? $vue->name.' • défaut' : $vue->name)
                ->icon('heroicon-o-eye')
                ->action(function () use ($vue): void {
                    $this->tableSort = data_get($vue->sort, 'tableSort');
                    $this->tableFilters = $vue->filters ?? [];
                    $this->resetTable();
                }))
            ->all();

        $actions[] = Action::make('enregistrerVue')
            ->label('Enregistrer la vue actuelle')
            ->icon('heroicon-o-bookmark')
            ->schema([
                TextInput::make('name')
                    ->label('Nom de la vue')
                    ->placeholder('ex : Chauds à relancer')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_default')
                    ->label('Définir comme vue par défaut'),
            ])
            ->action(function (array $data) use ($record): void {
                CustomView::create([
                    'custom_table_id' => $record->getKey(),
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
