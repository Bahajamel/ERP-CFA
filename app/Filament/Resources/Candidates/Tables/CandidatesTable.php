<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\CandidateStatut;
use App\Livret\LivrablesArchive;
use App\Models\Candidate;
use App\Models\Need;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
                    ->visible(fn ($record): bool => filled($record->currentState()->transitions()))
                    ->schema(fn ($record): array => [
                        Select::make('to')
                            ->label('Nouveau statut')
                            ->options(collect($record->currentState()->transitions())
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
                Action::make('planifierEntretien')
                    ->label('Planifier un entretien')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    // Avant décision finale uniquement : l'entretien pilote le statut.
                    ->visible(fn (Candidate $record): bool => ! $record->statut->estFinal())
                    ->modalHeading(fn (Candidate $record): string => "Planifier un entretien — {$record->nom_complet}")
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
                Action::make('envoyerMatching')
                    ->label('Envoyer vers Matching')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    // Étape suivante du cycle : uniquement pour les candidats acceptés.
                    ->visible(fn (Candidate $record): bool => $record->statut === CandidateStatut::Accepte)
                    ->modalHeading(fn (Candidate $record): string => "Envoyer {$record->nom_complet} vers le Matching")
                    ->schema([
                        Select::make('need_id')
                            ->label('Besoin entreprise')
                            ->options(fn (): array => Need::query()->ouverts()->with('company')->get()
                                ->mapWithKeys(fn (Need $n): array => [
                                    $n->id => $n->intitule_poste.($n->company ? ' — '.$n->company->raison_sociale : ''),
                                ])->all())
                            ->searchable()
                            ->required()
                            ->helperText('Besoins ouverts uniquement. Si le candidat a trouvé son entreprise '
                                .'lui-même, utilisez « Entreprise trouvée par le candidat » dans le module Matching.'),
                    ])
                    ->action(function (Candidate $record, array $data): void {
                        try {
                            app(CycleApprenant::class)->envoyerVersMatching($record, Need::query()->findOrFail($data['need_id']));
                            Notification::make()->success()
                                ->title('Candidat envoyé au Matching')
                                ->body('Un matching « En recherche » a été créé pour ce besoin.')
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
}
