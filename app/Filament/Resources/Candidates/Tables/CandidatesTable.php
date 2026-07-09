<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\CandidateStatut;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Livret\LivrablesArchive;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Support\Arr;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('email')
                    ->label('Contact')
                    ->description(fn ($record) => $record->telephone)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('formationVisee.libelle')
                    ->label('Formation visée')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('commercial.name')
                    ->label('Commercial')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CandidateStatut::class),
                SelectFilter::make('formation_visee_id')
                    ->label('Formation visée')
                    ->relationship('formationVisee', 'libelle'),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
                        \Filament\Forms\Components\DatePicker::make('date_entretien')
                            ->label('Date')->displayFormat('d/m/Y')->native(false)->required(),
                        \Filament\Forms\Components\TimePicker::make('heure_debut')
                            ->label('Heure de début')->seconds(false)->required(),
                        \Filament\Forms\Components\TimePicker::make('heure_fin')
                            ->label('Heure de fin')->seconds(false)->required()->after('heure_debut'),
                        Select::make('mode')
                            ->label('Mode')
                            ->options(\App\Enums\EntretienMode::class)
                            ->default(\App\Enums\EntretienMode::Presentiel->value)
                            ->required(),
                    ])
                    ->action(function (Candidate $record, array $data): void {
                        try {
                            $entretien = $record->entretiens()->create($data + [
                                'statut' => \App\Enums\EntretienStatut::Planifie->value,
                                'responsable_id' => auth()->id(),
                            ]);
                        } catch (\Illuminate\Validation\ValidationException $e) {
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
                    ->url(fn (Candidate $record): string => \App\Filament\Resources\Entretiens\EntretienResource::getUrl(
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
                            ->options(fn (): array => Need::query()->ouverts()->with('company')->get()
                                ->mapWithKeys(fn (Need $n): array => [
                                    $n->id => $n->intitule_poste.($n->company ? ' — '.$n->company->raison_sociale : ''),
                                ])->all())
                            ->searchable()
                            ->live()
                            ->helperText('Optionnel : laissez vide pour lancer une recherche d\'entreprise. '
                                .'Choisissez une offre pour envoyer une proposition (CV requis).'),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateHeading('Aucun candidat pour le moment')
            ->emptyStateDescription('Créez votre premier candidat : il démarre en « Entretien à planifier ». '
                .'Planifiez son entretien, puis acceptez-le ou refusez-le — l\'acceptation ouvre automatiquement le Matching.');
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
