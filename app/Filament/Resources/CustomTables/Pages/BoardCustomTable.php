<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Enums\CustomFieldType;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Models\CustomFieldDefinition;
use App\Models\CustomRecord;
use App\Models\CustomTable;
use App\Models\CustomTableShare;
use App\Models\CustomView;
use App\Models\User;
use App\Support\BoardNavigation;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            // Lien public PROPRE à ce tableau : le formulaire crée une ligne dans CE
            // tableau. Le libellé s'adapte au module (candidature, entreprise, offre).
            // On peut y activer/désactiver le formulaire et régénérer le lien.
            Action::make('lienCandidature')
                ->label(BoardNavigation::libelleLien($record->context))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->can('update', $record) ?? false)
                ->modalHeading(BoardNavigation::libelleLien($record->context).' de ce tableau')
                ->modalDescription('Partagez ce lien : la personne remplit le formulaire et une ligne est créée dans ce tableau, sans accès à l\'ERP.')
                ->modalSubmitActionLabel('Enregistrer')
                ->modalCancelActionLabel('Fermer')
                ->fillForm(fn (): array => ['public_enabled' => $record->public_enabled])
                ->schema([
                    Placeholder::make('lien')
                        ->hiddenLabel()
                        ->content(fn () => $record->public_enabled
                            ? view('filament.candidature-lien', ['lien' => $record->lienCandidature()])
                            : 'Le formulaire public est désactivé. Activez-le ci-dessous pour obtenir un lien fonctionnel.'),
                    Toggle::make('public_enabled')
                        ->label('Formulaire public activé')
                        ->helperText('Décochez pour fermer le formulaire : le lien ne créera plus de ligne.'),
                ])
                ->extraModalFooterActions([
                    Action::make('regenererLien')
                        ->label('Régénérer le lien')
                        ->icon('heroicon-o-arrow-path')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Régénérer le lien ?')
                        ->modalDescription('L\'ancien lien cessera immédiatement de fonctionner ; un nouveau lien est généré.')
                        ->action(function () use ($record): void {
                            $record->regenererToken();
                            Notification::make()->success()
                                ->title('Nouveau lien généré')
                                ->body('Rouvrez la fenêtre « Lien » pour copier le nouveau lien.')
                                ->send();
                        }),
                ])
                ->action(function (array $data) use ($record): void {
                    $record->update(['public_enabled' => (bool) ($data['public_enabled'] ?? false)]);
                    Notification::make()->success()->title('Lien mis à jour')->send();
                }),

            // Inviter des membres du CFA à consulter/modifier ce tableau (privé par
            // défaut). Réservé au gestionnaire (créateur / Administrateur / Direction).
            Action::make('partager')
                ->label('Partager')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->can('share', $record) ?? false)
                ->modalHeading('Partager « '.$record->name.' »')
                ->modalDescription('Ce tableau est privé. Invitez des membres de votre CFA à le consulter (Lecture) '
                    .'ou à le modifier (Modification). Le créateur et la Direction y ont toujours accès.')
                ->modalSubmitActionLabel('Enregistrer les accès')
                ->modalWidth('2xl')
                ->fillForm(fn (): array => ['partages' => $record->partages()
                    ->get()
                    ->map(fn (CustomTableShare $p): array => ['user_id' => $p->user_id, 'role' => $p->role])
                    ->all()])
                ->schema([
                    Repeater::make('partages')
                        ->hiddenLabel()
                        ->addActionLabel('Inviter une personne')
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => filled($state['user_id'] ?? null)
                            ? (User::find($state['user_id'])?->name ?? 'Personne') : 'Nouvelle invitation')
                        ->schema([
                            Select::make('user_id')
                                ->label('Membre')
                                ->options(fn (): array => $this->membresCfa($record))
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->native(false),
                            Select::make('role')
                                ->label('Niveau d\'accès')
                                ->options(CustomTableShare::roles())
                                ->default(CustomTableShare::ROLE_LECTURE)
                                ->required()
                                ->native(false),
                        ]),
                ])
                ->action(fn (array $data) => $this->synchroniserPartages($record, $data['partages'] ?? [])),

            $this->importExportAction(),

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

    /**
     * Membres du CFA invitables (hors créateur, qui a déjà accès) : id => nom.
     *
     * @return array<int, string>
     */
    private function membresCfa(CustomTable $record): array
    {
        return User::query()
            ->when($record->organisation_id !== null,
                fn ($q) => $q->whereHas('organisations', fn ($o) => $o->whereKey($record->organisation_id)))
            ->whereKeyNot($record->created_by)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Applique la liste des invitations : crée/actualise les partages, retire ceux
     * qui ne sont plus listés, et prévient les personnes nouvellement invitées.
     *
     * @param  array<int, array{user_id?: mixed, role?: mixed}>  $lignes
     */
    private function synchroniserPartages(CustomTable $record, array $lignes): void
    {
        $anciens = $record->partages()->pluck('user_id')->all();
        $gardes = [];

        foreach ($lignes as $ligne) {
            $userId = (int) ($ligne['user_id'] ?? 0);
            if ($userId <= 0 || $userId === $record->created_by) {
                continue;
            }

            $role = in_array($ligne['role'] ?? null, [CustomTableShare::ROLE_LECTURE, CustomTableShare::ROLE_MODIFICATION], true)
                ? $ligne['role']
                : CustomTableShare::ROLE_LECTURE;

            $record->partages()->updateOrCreate(
                ['user_id' => $userId],
                ['role' => $role, 'organisation_id' => $record->organisation_id],
            );

            $gardes[] = $userId;
        }

        // Retire les accès révoqués.
        $record->partages()->whereNotIn('user_id', $gardes ?: [0])->delete();

        // Prévient les personnes nouvellement invitées (cloche de l'ERP).
        $nouveaux = array_diff($gardes, $anciens);
        if ($nouveaux !== []) {
            $destinataires = User::query()->whereKey($nouveaux)->get();

            foreach ($destinataires as $destinataire) {
                Notification::make()
                    ->title('Tableau partagé avec vous : '.$record->name)
                    ->body('Vous avez été invité à accéder à ce tableau personnalisé.')
                    ->icon('heroicon-o-user-plus')
                    ->success()
                    ->sendToDatabase($destinataire);
            }
        }

        Notification::make()->success()->title('Accès mis à jour')->send();
    }

    /** Après suppression : retour au module d'origine, sinon à la liste Administration. */
    private function urlRetour(CustomTable $record): string
    {
        $contextes = BoardNavigation::contextes();

        return isset($contextes[$record->context])
            ? $contextes[$record->context]['resource']::getUrl('index')
            : CustomTableResource::getUrl('index');
    }

    /** Groupe d'actions Import / Export CSV des lignes du tableau. */
    protected function importExportAction(): ActionGroup
    {
        $record = $this->getRecord();

        $exporter = Action::make('exporterCsv')
            ->label('Exporter (CSV)')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (): StreamedResponse => $this->exporterCsv($record));

        $importer = Action::make('importerCsv')
            ->label('Importer (CSV)')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => $record->colonnes->isNotEmpty()
                && (Auth::user()?->can('create', CustomRecord::class) ?? false))
            ->modalHeading('Importer des lignes (CSV)')
            ->modalDescription('Fichier CSV séparé par « ; », 1re ligne = en-têtes correspondant aux noms de colonnes '
                .'(colonnes inconnues ignorées). Astuce : exportez d\'abord pour obtenir le bon format.')
            ->modalSubmitActionLabel('Importer')
            ->schema([
                FileUpload::make('fichier')
                    ->label('Fichier CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(fn (array $data) => $this->importerCsv($record, $data['fichier']));

        return ActionGroup::make([$exporter, $importer])
            ->label('Import / Export')
            ->icon('heroicon-o-arrows-up-down')
            ->button()
            ->color('gray');
    }

    public function exporterCsv(CustomTable $record): StreamedResponse
    {
        $colonnes = $record->colonnes;
        $nom = (Str::slug($record->name) ?: 'tableau').'.csv';

        return response()->streamDownload(function () use ($record, $colonnes): void {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
            fputcsv($sortie, $colonnes->pluck('label')->all(), ';');

            $record->records()->orderBy('id')->chunk(500, function ($lignes) use ($sortie, $colonnes): void {
                foreach ($lignes as $ligne) {
                    fputcsv($sortie, $colonnes->map(function ($def) use ($ligne): string {
                        $v = data_get($ligne->data, $def->key);

                        return match (true) {
                            is_array($v) => implode(', ', $v),
                            $v === true => 'Oui',
                            $v === false => 'Non',
                            default => (string) ($v ?? ''),
                        };
                    })->all(), ';');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function importerCsv(CustomTable $record, $fichier): void
    {
        $compte = $this->importerDepuisChemin($record, $fichier->getRealPath());

        $this->resetTable();
        Notification::make()->success()->title($compte.' ligne(s) importée(s)')->send();
    }

    /** Importe les lignes d'un fichier CSV (séparateur « ; »), renvoie le nombre créé. */
    public function importerDepuisChemin(CustomTable $record, string $chemin): int
    {
        $handle = fopen($chemin, 'r');
        if ($handle === false) {
            return 0;
        }

        $entetes = fgetcsv($handle, 0, ';') ?: [];
        // En-tête (minuscule, nettoyé) => clé de colonne.
        $parLabel = $record->colonnes->keyBy(fn ($d): string => mb_strtolower(trim($d->label)));
        $indexCle = [];
        foreach ($entetes as $i => $entete) {
            $cle = mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $entete)));
            if ($parLabel->has($cle)) {
                $indexCle[$i] = $parLabel->get($cle)->key;
            }
        }

        $compte = 0;
        while (($ligne = fgetcsv($handle, 0, ';')) !== false) {
            $data = [];
            foreach ($indexCle as $i => $cle) {
                $valeur = trim(strip_tags((string) ($ligne[$i] ?? '')));
                if ($valeur !== '') {
                    $data[$cle] = $valeur;
                }
            }

            if ($data !== []) {
                CustomRecord::create(['custom_table_id' => $record->getKey(), 'data' => $data]);
                $compte++;
            }
        }

        fclose($handle);

        return $compte;
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

        // Droit de modifier CE tableau (gestionnaire ou invité « modification ») :
        // gouverne l'édition en ligne, l'ajout de lignes et le réordonnancement.
        $peutModifier = $record->modifiablePar(Auth::user());

        // Colonnes dynamiques ÉDITABLES en ligne (façon Monday) + recherche globale.
        $colonnesDynamiques = CustomFields::colonnes($colonnes, 'data', editable: $peutModifier);
        if ($colonnesDynamiques !== []) {
            $colonnesDynamiques[0]->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('CAST(data AS TEXT) LIKE ?', ['%'.$search.'%']));
        }

        // Glisser-déposer des lignes (ordre manuel façon Monday) : réservé à qui
        // peut modifier CE tableau (gestionnaire ou invité « modification »).
        $peutReordonner = $peutModifier && (Auth::user()?->can('custom_records.update') ?? false);

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
            // Poignée de glissement des lignes (met à jour la colonne « position »).
            ->reorderable('position', $peutReordonner)
            // Bouton explicite (au lieu de l'icône discrète) : clic → mode
            // réorganisation, on glisse les lignes, re-clic → terminé.
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action
                    ->button()
                    ->icon('heroicon-o-arrows-up-down')
                    ->label($isReordering ? 'Terminer le classement' : 'Réorganiser les lignes'),
            )
            ->filters(CustomFields::filtres($colonnes, 'data'))
            // Groupes repliables (façon Monday) par colonne Statut/Liste.
            ->groups(CustomFields::groupes($colonnes, 'data'))
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une ligne')
                    ->visible(fn (): bool => $colonnes->isNotEmpty()
                        && $peutModifier
                        && (Auth::user()?->can('create', CustomRecord::class) ?? false))
                    ->schema(CustomFields::champs($colonnes, 'data'))
                    ->using(fn (array $data): CustomRecord => CustomRecord::create(
                        $data + ['custom_table_id' => $record->getKey()],
                    )),
                // « Ajouter une colonne » placé juste à côté de « Ajouter une ligne ».
                CustomFields::gererActionTableau($record->getKey(), 'gererColonnes')
                    ->visible(fn (): bool => Auth::user()?->can('update', $record) ?? false),
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
            // Ordre manuel (glisser-déposer) par défaut ; retomber sur l'id garde
            // un ordre stable quand les positions sont égales.
            ->defaultSort('position', 'asc')
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

                    // Restaure aussi les colonnes visibles et leur ordre.
                    if (filled($vue->column_order)) {
                        $this->applyTableColumnManager($vue->column_order, wasReordered: true);
                    }

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
                    // État des colonnes (ordre + visibilité), tel que géré par Filament.
                    'column_order' => $this->tableColumns,
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
