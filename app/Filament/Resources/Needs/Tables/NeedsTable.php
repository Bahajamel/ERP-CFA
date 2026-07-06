<?php

namespace App\Filament\Resources\Needs\Tables;

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Need;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('intitule_poste')
                    ->label('Poste')
                    ->description(fn ($record) => $record->company?->raison_sociale)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('localisation')
                    ->label('Lieu')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('nb_postes')
                    ->label('Postes')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('postes_restants')
                    ->label('Restants')
                    ->state(fn (Need $record): int => $record->postesRestants())
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'success' : 'warning')
                    ->alignCenter()
                    ->tooltip('Postes encore à pourvoir (demandés − candidats acceptés)'),
                TextColumn::make('matchings_count')
                    ->label('Candidats proposés')
                    ->counts('matchings')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('date_demarrage')
                    ->label('Démarrage')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('date_cloture')
                    ->label('Clôturé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ouverts')
                    ->label('Besoins ouverts uniquement')
                    ->query(fn (Builder $query): Builder => $query->ouverts()),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(NeedStatut::class),
                SelectFilter::make('company_id')
                    ->label('Entreprise')
                    ->relationship('company', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('changerStatut')
                    ->label('Changer le statut')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (Need $record): bool => filled($record->currentState()->transitions()))
                    ->schema(fn (Need $record): array => [
                        Select::make('to')
                            ->label('Nouveau statut')
                            ->options(collect($record->currentState()->transitions())
                                ->mapWithKeys(fn (NeedStatut $s): array => [$s->value => $s->getLabel()])
                                ->all())
                            ->required(),
                        Textarea::make('comment')
                            ->label('Commentaire (optionnel)'),
                    ])
                    ->action(function (Need $record, array $data): void {
                        try {
                            $record->transitionTo(NeedStatut::from($data['to']), $data['comment'] ?? null);
                            Notification::make()->success()->title('Statut mis à jour')->send();
                        } catch (InvalidTransitionException $e) {
                            Notification::make()->danger()->title('Transition refusée')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('trouverCandidats')
                    ->label('Trouver des candidats')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->modalHeading(fn (Need $record): string => "Candidats compatibles — {$record->intitule_poste}")
                    ->modalSubmitActionLabel('Proposer les candidats cochés')
                    ->schema(fn (Need $record): array => [
                        CheckboxList::make('candidates')
                            ->label('Candidats compatibles (classés par score de compatibilité)')
                            ->options(
                                $record->candidatsCompatibles()
                                    ->mapWithKeys(fn (array $row): array => [
                                        $row['candidate']->id => "{$row['candidate']->nom_complet} — {$row['score']} pts · {$row['explication']}",
                                    ])
                                    ->all()
                            )
                            ->helperText('Aucune ligne = aucun candidat compatible (formation, disponibilité…). Coche ceux à proposer.')
                            ->bulkToggleable()
                            ->columns(1),
                    ])
                    ->action(function (Need $record, array $data): void {
                        $created = 0;

                        foreach ($data['candidates'] ?? [] as $candidateId) {
                            if (! $record->matchings()->where('candidate_id', $candidateId)->exists()) {
                                $record->matchings()->create([
                                    'candidate_id' => $candidateId,
                                    'statut' => MatchingStatut::Propose,
                                    'assigned_by' => auth()->id(),
                                ]);
                                $created++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title($created > 0 ? "{$created} candidat(s) proposé(s)" : 'Aucun candidat proposé')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-briefcase')
            ->emptyStateHeading('Aucun besoin de recrutement')
            ->emptyStateDescription('Enregistrez le premier poste à pourvoir d\'une entreprise : le matching pourra ensuite proposer des candidats compatibles.');
    }
}
