<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\CandidateStatut;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Filament\Resources\Entretiens\EntretienResource;
use App\Livret\LivrablesArchive;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\Rules\TelephoneInternational;
use App\StateMachine\InvalidTransitionException;
use App\Support\CustomFields;
use App\Support\Indicatifs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CandidatesTable
{
    /**
     * Colonnes natives « renommables / redimensionnables » par CFA (couche façon
     * Monday) : clé de colonne => libellé d'origine. Sert au modal « Renommer les
     * colonnes » et à la portée du glisser-déposer de largeur.
     */
    public const COLONNES_PERSONNALISABLES = [
        'identite' => 'Nom candidat',
        'statut' => 'Statut',
        'commercial.name' => 'Référent',
        'formationVisee.libelle' => 'Formation',
        'ville' => 'Ville',
        'disponibilite' => 'Disponibilité',
        'documents_count' => 'Documents',
        'progression' => 'Progression',
        'email' => 'Contact',
        'created_at' => 'Créé le',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query->with(['formationVisee', 'commercial', 'interactions', 'matchings', 'admissions']);
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            // Colonnes « Base Candidats » (maquette) : nom, statut, référent,
            // formation, ville, dernier contact, prochaine action, disponibilité,
            // documents. Colonnes secondaires masquables via le menu « Colonnes ».
            ->columns(CustomFields::appliquerReglages([
                ViewColumn::make('identite')
                    ->label('Nom candidat')
                    ->view('filament.candidates.col-identite')
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom'])
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('commercial.name')
                    ->label('Référent')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('formationVisee.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ville')
                    ->label('Ville')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('disponibilite')
                    ->label('Disponibilité')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        blank($state) => 'gray',
                        str_contains(Str::lower($state), 'immédiat'), str_contains(Str::lower($state), 'immediat') => 'success',
                        str_contains(Str::lower($state), 'semaine') => 'info',
                        str_contains(Str::lower($state), 'mois') => 'warning',
                        str_contains(Str::lower($state), 'confirm') => 'warning',
                        str_contains(Str::lower($state), 'non') => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('documents_count')
                    ->label('Documents')
                    ->counts('documents')
                    ->badge()
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->toggleable(),
                ViewColumn::make('progression')
                    ->label('Progression')
                    ->view('filament.candidates.progression')
                    ->toggleable(),
                // Colonnes secondaires, masquées par défaut (réactivables).
                TextColumn::make('email')
                    ->label('Contact')
                    ->description(fn ($record) => $record->telephone)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Colonnes personnalisées du CFA (masquables), s'il en a défini.
                ...CustomFields::tableColumns('candidate'),
            ], 'candidate'))
            // Réorganisation « façon Monday » : le CFA glisse-dépose les EN-TÊTES
            // (JS dédié) et l'ordre est mémorisé PAR CFA (custom_column_settings.position,
            // appliqué dans CustomFields::appliquerReglages). On n'utilise donc pas
            // reorderableColumns() natif (ordre par utilisateur, incompatible).
            // Le menu « Colonnes » conserve l'affichage/masquage (colonnes toggleable).
            // Vue « façon Monday » : les candidats sont réunis sur UNE page, répartis
            // en groupes repliables (blocs). Par défaut regroupés par statut — comme
            // les groupes Monday — l'utilisateur peut changer de critère via « Grouper ».
            ->groups([
                Group::make('statut')
                    ->label('Statut')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Candidate $record): string => $record->statut?->getLabel() ?? '—'),
                Group::make('commercial.name')->label('Référent')->collapsible(),
                Group::make('formationVisee.libelle')->label('Formation')->collapsible(),
                Group::make('ville')->label('Ville')->collapsible(),
                Group::make('disponibilite')->label('Disponibilité')->collapsible(),
            ])
            // Groupé par statut à l'ouverture (board Monday orienté candidats).
            ->defaultGroup('statut')
            // Clic sur une ligne = ouvre le modal d'édition rapide (façon Monday).
            // Le panneau « Focus du jour » reste accessible via le bouton « Aperçu ».
            ->recordAction('modifierLigne')
            ->recordUrl(null)
            // Glisser-déposer des lignes (ordre manuel façon Monday, cohérent avec
            // les tableaux personnalisés) : réservé à qui peut gérer les candidats.
            // Le drag réordonne au sein du groupe (statut) affiché.
            ->reorderable('position', auth()->user()?->can('access_candidates') ?? false)
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action
                    ->button()
                    ->icon('heroicon-o-arrows-up-down')
                    ->label($isReordering ? 'Terminer le classement' : 'Réorganiser les lignes'),
            )
            ->filters([
                // Filtre sur la colonne « Progression » : c'est la question que
                // l'on se pose devant cet écran (« qui est bloqué où ? »), et rien
                // ne permettait d'y répondre — le statut seul ne dit pas si un
                // candidat accepté cherche encore une entreprise ou attend son
                // admission.
                SelectFilter::make('etape')
                    // Même nom que la colonne qu'il filtre : on cherche « Progression »
                    // dans la barre parce qu'on la voit dans le tableau.
                    ->label('Progression')
                    ->options(Candidate::etapesProgression())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->aEtape($data['value'])
                        : $query),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CandidateStatut::class),
                SelectFilter::make('formation_visee_id')
                    ->label('Formation')
                    ->relationship('formationVisee', 'libelle'),
                SelectFilter::make('commercial_id')
                    ->label('Commercial')
                    ->relationship('commercial', 'name'),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn (): array => Candidate::query()
                        ->whereNotNull('source')
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->all()),
            ])
            // Listes déroulantes toujours visibles en barre au-dessus du tableau
            // (au lieu du menu déroulant « Filtres »), comme le workspace attendu.
            // Filtres instantanés (sans bouton « Appliquer ») pour une barre compacte.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 4,
            ])
            ->recordActions([
                // Sélectionne le candidat dans le panneau « Focus du jour »
                // (clic sur la ligne = même action, sans navigation).
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Candidate $record, $livewire) => $livewire->focusId = $record->getKey()),
                // Édition rapide « façon Monday » : clic sur la ligne → modal large
                // (horizontal) pour changer directement le contenu des colonnes,
                // sans quitter la liste. Réservée à qui peut modifier un candidat.
                self::modifierLigneAction(),
                ActionGroup::make([
                    Action::make('changerStatut')
                        ->label('Changer le statut')
                        ->icon('heroicon-o-arrows-right-left')
                    // Cycle apprenant : le statut est piloté par les Entretiens.
                    // On n'affiche que les transitions réellement possibles ici
                    // (ex. « Refuser ») — « Accepté »/« Entretien prévu » passent
                    // par la section Entretiens et sont donc masqués tant qu'ils
                    // ne sont pas atteignables.
                        ->visible(fn ($record): bool => filled($record->allowedTransitions()))
                        ->modalDescription('L\'acceptation et le passage à « Entretien prévu » se font via la '
                            .'section Entretiens (planifier un entretien, puis accepter après l\'avoir réalisé). '
                            .'Ce menu ne propose que les changements possibles manuellement.')
                        ->schema(fn ($record): array => [
                            Select::make('to')
                                ->label('Nouveau statut')
                                ->options(collect($record->allowedTransitions())
                                    ->mapWithKeys(fn (CandidateStatut $s) => [$s->value => $s->getLabel()])
                                    ->all())
                                ->required(),
                            Textarea::make('comment')
                                ->label('Commentaire (optionnel)'),
                        ])
                        ->action(function ($record, array $data): void {
                            try {
                                $record->transitionTo(CandidateStatut::from($data['to']), $data['comment'] ?? null);
                                Notification::make()->success()->title('Statut mis à jour')->send();
                            } catch (InvalidTransitionException $e) {
                                Notification::make()->danger()->title('Transition refusée')->body($e->getMessage())->send();
                            }
                        }),
                    // Point de création UNIQUE d'un entretien (la section Entretiens
                    // ne fait que les afficher / gérer). Masqué s'il existe déjà un
                    // entretien actif → on propose alors « Gérer l'entretien ».
                    Action::make('planifierEntretien')
                        ->label('Planifier un entretien')
                        ->icon('heroicon-o-calendar-days')
                        ->color('info')
                        ->visible(fn (Candidate $record): bool => ! $record->statut->estFinal()
                            && $record->entretienActif() === null)
                        ->modalHeading(fn (Candidate $record): string => "Planifier un entretien — {$record->nom_complet}")
                        ->modalDescription('L\'entretien apparaîtra dans la section Entretiens et le candidat passera '
                            .'automatiquement à « Entretien prévu ».')
                        ->schema([
                            DatePicker::make('date_entretien')
                                ->label('Date')->displayFormat('d/m/Y')->native(false)->required(),
                            TimePicker::make('heure_debut')
                                ->label('Heure de début')->seconds(false)->required(),
                            TimePicker::make('heure_fin')
                                ->label('Heure de fin')->seconds(false)->required()->after('heure_debut'),
                            Select::make('mode')
                                ->label('Mode')
                                ->options(EntretienMode::class)
                                ->default(EntretienMode::Presentiel->value)
                                ->required(),
                        ])
                        ->action(function (Candidate $record, array $data): void {
                            try {
                                $entretien = $record->entretiens()->create($data + [
                                    'statut' => EntretienStatut::Planifie->value,
                                    'responsable_id' => auth()->id(),
                                ]);
                            } catch (ValidationException $e) {
                                Notification::make()->danger()->title('Planification impossible')
                                    ->body(collect($e->errors())->flatten()->first())->send();

                                return;
                            }

                            Notification::make()->success()
                                ->title('Entretien planifié')
                                ->body($entretien->creneauLisible().' — le candidat passe à « Entretien prévu ».')
                                ->send();
                        }),
                    // Un entretien est déjà en cours : on renvoie vers la section
                    // Entretiens pour le gérer (reprogrammer, réaliser, décider).
                    Action::make('gererEntretien')
                        ->label('Gérer l\'entretien')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->visible(fn (Candidate $record): bool => ! $record->statut->estFinal()
                            && $record->entretienActif() !== null)
                        ->url(fn (Candidate $record): string => EntretienResource::getUrl(
                            'edit',
                            ['record' => $record->entretienActif()],
                        )),
                    Action::make('envoyerMatching')
                        ->label('Envoyer vers Matching')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        // Étape suivante du cycle : uniquement pour les candidats acceptés.
                        ->visible(fn (Candidate $record): bool => $record->statut === CandidateStatut::Accepte)
                        ->modalHeading(fn (Candidate $record): string => "Envoyer {$record->nom_complet} vers le Matching")
                        ->modalDescription('Sans offre : le candidat part « En recherche » d\'entreprise. '
                            .'Avec une offre : une proposition est envoyée (un CV est alors obligatoire).')
                        ->schema([
                            Select::make('need_id')
                                ->label('Offres proposées')
                                // Intelligence : on ne propose que les besoins ouverts dont la
                                // « Formation visée » correspond à celle du candidat (une offre
                                // d'une autre formation n'a aucun sens ici).
                                ->options(fn (Candidate $record): array => $record->offresProposables()
                                    ->mapWithKeys(fn (Need $n): array => [
                                        $n->id => $n->intitule_poste.($n->company ? ' — '.$n->company->raison_sociale : ''),
                                    ])->all())
                                ->searchable()
                                ->live()
                                ->helperText(fn (Candidate $record): string => $record->formationVisee
                                    ? 'Offres ouvertes pour la formation « '.$record->formationVisee->libelle.' ». '
                                        .'Laissez vide pour une recherche d\'entreprise ; choisissez une offre pour envoyer une proposition (CV requis).'
                                    : 'Aucune formation visée sur la fiche : toutes les offres ouvertes sont proposées. '
                                        .'Laissez vide pour une recherche d\'entreprise ; choisissez une offre pour envoyer une proposition (CV requis).')
                                ->placeholder('Aucune offre ouverte pour cette formation — laissez vide'),
                            // CV requis uniquement si une offre est sélectionnée.
                            Radio::make('cv_source')
                                ->label('CV à joindre à la proposition')
                                ->options(fn (Candidate $record): array => array_filter([
                                    'existant' => $record->hasCv() ? 'Utiliser le CV existant du candidat' : null,
                                    'nouveau' => 'Ajouter un nouveau CV',
                                ]))
                                ->default(fn (Candidate $record): string => $record->hasCv() ? 'existant' : 'nouveau')
                                ->visible(fn (Get $get): bool => filled($get('need_id')))
                                ->required(fn (Get $get): bool => filled($get('need_id')))
                                ->live(),
                            FileUpload::make('cv_nouveau')
                                ->label('Nouveau CV')
                                ->disk('public')
                                ->directory('cv-propositions')
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                ])
                                ->maxSize(5120)
                                ->helperText('PDF, DOC ou DOCX (5 Mo max).')
                                ->visible(fn (Get $get): bool => filled($get('need_id')) && $get('cv_source') === 'nouveau')
                                ->required(fn (Get $get): bool => filled($get('need_id')) && $get('cv_source') === 'nouveau'),
                        ])
                        ->action(function (Candidate $record, array $data): void {
                            $needId = $data['need_id'] ?? null;

                            try {
                                // Cas 1 : aucune offre → recherche d'entreprise (« En recherche »).
                                if (blank($needId)) {
                                    app(CycleApprenant::class)->envoyerVersMatching($record, null);
                                    Notification::make()->success()
                                        ->title('Candidat en recherche d\'entreprise')
                                        ->body('Un dossier Matching « En recherche » est ouvert (aucune offre associée).')
                                        ->send();

                                    return;
                                }

                                // Cas 2 : offre sélectionnée → proposition (CV obligatoire).
                                $need = Need::query()->findOrFail($needId);
                                self::resoudreCvProposition($record, $data);

                                $matching = app(CycleApprenant::class)
                                    ->proposerSurOffre($record, $need, cvDisponible: $record->fresh()->hasCv());

                                self::joindreCvAuMatching($record, $matching);

                                Notification::make()->success()
                                    ->title('Proposition envoyée')
                                    ->body('Matching « Proposition envoyée » créé pour ce candidat sur l\'offre choisie.')
                                    ->send();
                            } catch (CycleBloqueException $e) {
                                Notification::make()->danger()->title('Envoi impossible')->body($e->getMessage())->send();
                            }
                        }),
                    Action::make('entreprisesACibler')
                        ->label('Entreprises à cibler')
                        ->icon('heroicon-o-building-office-2')
                        ->color('info')
                        ->modalHeading(fn (Candidate $record): string => "Entreprises à cibler — {$record->nom_complet}")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer')
                        ->modalContent(fn (Candidate $record) => view('filament.candidates.entreprises-a-cibler', [
                            'cibles' => $record->entreprisesACibler(),
                        ])),
                    Action::make('couvertureMissions')
                        ->label('Missions CFA')
                        ->icon('heroicon-o-academic-cap')
                        ->color('gray')
                        ->visible(fn (): bool => auth()->user()?->can('access_documents') ?? false)
                        ->modalHeading(fn (Candidate $record): string => "Couverture des 14 missions CFA — {$record->nom_complet}")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer')
                        ->modalContent(fn (Candidate $record) => view('filament.candidates.couverture-missions', [
                            'record' => $record,
                        ])),
                    Action::make('livrablesZip')
                        ->label('Livrables (ZIP)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('gray')
                        ->visible(fn (Candidate $record): bool => (auth()->user()?->can('access_documents') ?? false)
                            && $record->documents()->where('source', 'livretrs')->exists())
                        ->action(function (Candidate $record) {
                            $zip = app(LivrablesArchive::class)->pour($record);

                            if ($zip === null) {
                                Notification::make()->title('Aucun livrable à télécharger')->warning()->send();

                                return null;
                            }

                            $nom = 'livrables_'.str($record->nom_complet)->slug().'.zip';

                            return response()->download($zip, $nom)->deleteFileAfterSend();
                        }),
                    ViewAction::make(),
                    EditAction::make(),
                    // Suppression = archivage en corbeille, motif OBLIGATOIRE. Le
                    // candidat et ses dossiers quittent toutes les listes ; purge
                    // définitive automatique après 30 jours (restauration possible).
                    Action::make('supprimer')
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Candidate $record): string => "Supprimer {$record->nom_complet} ?")
                        ->modalDescription('Le candidat et tous ses dossiers (matching, entretiens, contrat, '
                            .'admission, dossier OPCO…) seront retirés de toutes les listes et placés dans la '
                            .'Corbeille pendant '.Candidate::DELAI_PURGE_JOURS.' jours. Vous pourrez le restaurer '
                            .'avant la suppression définitive automatique.')
                        ->modalSubmitActionLabel('Placer dans la corbeille')
                        ->schema([
                            Textarea::make('motif_suppression')
                                ->label('Motif de suppression')
                                ->required()
                                ->rows(3)
                                ->placeholder('ex : doublon, candidature annulée, erreur de saisie…'),
                        ])
                        ->action(function (Candidate $record, array $data): void {
                            $record->archiver($data['motif_suppression']);

                            Notification::make()->success()
                                ->title('Candidat placé dans la corbeille')
                                ->body('Restaurable pendant '.Candidate::DELAI_PURGE_JOURS.' jours (section Corbeille).')
                                ->send();
                        }),
                ])
                    ->label('Plus')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('supprimerLot')
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Les candidats sélectionnés seront placés dans la Corbeille ('
                            .Candidate::DELAI_PURGE_JOURS.' jours) avant suppression définitive.')
                        ->schema([
                            Textarea::make('motif_suppression')
                                ->label('Motif de suppression')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Candidate $c) => $c->archiver($data['motif_suppression']));

                            Notification::make()->success()
                                ->title($records->count().' candidat(s) placé(s) dans la corbeille')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            // Ordre manuel (glisser-déposer) par défaut — au sein de chaque groupe
            // de statut. Le tri par colonne (nom, date…) reste possible via l'en-tête.
            ->defaultSort('position', 'asc')
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateHeading('Aucun candidat pour le moment')
            ->emptyStateDescription('Créez votre premier candidat : il démarre en « Entretien à planifier ». '
                .'Planifiez son entretien, puis acceptez-le ou refusez-le — l\'acceptation ouvre automatiquement le Matching.');
    }

    /**
     * Modal d'édition rapide « façon Monday » : large et horizontal (grille 3
     * colonnes), il édite directement les informations affichées dans le tableau
     * — identité, contact (avec le même contrôle téléphone international que le
     * formulaire), ville, formation, référent, disponibilité — ainsi que les
     * colonnes personnalisées du CFA. Le statut reste piloté par le cycle
     * apprenant (non éditable ici) et les pièces restent sur la fiche complète.
     */
    private static function modifierLigneAction(): Action
    {
        return Action::make('modifierLigne')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (Candidate $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalHeading(fn (Candidate $record): string => "Modifier — {$record->nom_complet}")
            ->modalDescription('Édition rapide des informations principales. Les pièces et le suivi détaillé restent sur la fiche.')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Enregistrer')
            ->fillForm(fn (Candidate $record): array => [
                'nom' => $record->nom,
                'prenom' => $record->prenom,
                'email' => $record->email,
                'telephone' => $record->telephone,
                'ville' => $record->ville,
                'formation_visee_id' => $record->formation_visee_id,
                'commercial_id' => $record->commercial_id,
                'disponibilite' => $record->disponibilite,
                'date_disponibilite' => $record->date_disponibilite,
                'custom_fields' => $record->custom_fields ?? [],
            ])
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('nom')
                        ->label('Nom')
                        ->required(),
                    TextInput::make('prenom')
                        ->label('Prénom')
                        ->required(),
                    TextInput::make('email')
                        ->label('Adresse e-mail')
                        ->email()
                        ->requiredWithout('telephone')
                        ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                    Select::make('indicatif_pays')
                        ->label('Pays')
                        ->options(Indicatifs::options())
                        ->default(Indicatifs::defaut())
                        ->selectablePlaceholder(false)
                        ->searchable()
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('telephone', Indicatifs::appliquer($get('telephone'), $state)))
                        ->afterStateHydrated(function (Select $component, Get $get): void {
                            if (filled($get('telephone'))) {
                                $component->state(Indicatifs::detecter($get('telephone')));
                            }
                        }),
                    TextInput::make('telephone')
                        ->label('Téléphone')
                        ->tel()
                        ->placeholder('ex : +33 6 12 34 56 78')
                        ->rule(new TelephoneInternational)
                        ->requiredWithout('email')
                        ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                    TextInput::make('ville')
                        ->label('Ville'),
                    Select::make('formation_visee_id')
                        ->label('Formation')
                        ->relationship('formationVisee', 'libelle')
                        ->searchable()
                        ->preload(),
                    Select::make('commercial_id')
                        ->label('Référent')
                        ->relationship('commercial', 'name')
                        ->searchable()
                        ->preload(),
                    TextInput::make('disponibilite')
                        ->label('Disponibilité')
                        ->placeholder('ex : Immédiate, Sous 1 mois'),
                    DatePicker::make('date_disponibilite')
                        ->label('Disponible à partir du')
                        ->displayFormat('d/m/Y')
                        ->native(false),
                ]),
                // Colonnes personnalisées du CFA (éditables ici aussi), s'il en a.
                ...CustomFields::formSchema('candidate'),
            ])
            ->action(function (Candidate $record, array $data): void {
                $record->update($data);

                Notification::make()->success()->title('Candidat mis à jour')->send();
            });
    }

    /** Scope rapide courant lu sur la page (null hors ListCandidates). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Applique un filtre rapide « orienté action » à la requête du tableau.
     * Réutilisé par les compteurs des blocs (ListCandidates) pour rester cohérent.
     *
     *  - a_planifier : entretien encore à planifier (premier échange à organiser) ;
     *  - a_decider : entretien réalisé, décision Accepté/Refusé en attente ;
     *  - a_orienter : candidat accepté mais pas encore envoyé vers une entreprise.
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_planifier' => $query->where('statut', CandidateStatut::EntretienAPlanifier->value),
            'a_decider' => $query->where('statut', CandidateStatut::EntretienRealise->value),
            'a_orienter' => $query->where('statut', CandidateStatut::Accepte->value)
                ->whereDoesntHave('matchings'),
            default => null,
        };
    }

    /**
     * Résout le CV d'une proposition : si l'utilisateur a ajouté un nouveau
     * CV, il devient le CV du candidat (collection « cv » + document GED), donc
     * disponible et tracé au profil. Le CV existant, lui, est réutilisé tel quel.
     */
    private static function resoudreCvProposition(Candidate $record, array $data): void
    {
        if (($data['cv_source'] ?? null) !== 'nouveau') {
            return;
        }

        $chemin = collect(Arr::wrap($data['cv_nouveau'] ?? []))->first();

        if (blank($chemin)) {
            return;
        }

        $media = $record->addMediaFromDisk($chemin, 'public')->toMediaCollection('cv');

        $document = $record->documents()->create([
            'type' => DocumentType::CvCandidat->value,
            'statut' => DocumentStatut::Recu->value,
            'source' => DocumentSource::Manuel->value,
            'nom_fichier' => 'CV candidat',
            'uploaded_by' => auth()->id(),
        ]);

        $media->copy($document, 'fichier');
    }

    /**
     * Joint au dossier Matching une copie du CV réellement transmis
     * (traçabilité de la proposition). Silencieux si aucun CV exploitable ou
     * si le matching en porte déjà un.
     */
    private static function joindreCvAuMatching(Candidate $record, Matching $matching): void
    {
        $media = $record->fresh()->cvMedia();

        if ($media === null || $matching->getFirstMedia('cv') !== null) {
            return;
        }

        $media->copy($matching, 'cv');
    }
}
