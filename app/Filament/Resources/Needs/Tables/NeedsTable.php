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
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query->with(['company', 'formation'])->withCount('matchings');
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            ->columns([
                ViewColumn::make('identite')
                    ->label('Offre')
                    ->view('filament.needs.col-identite')
                    ->searchable(['intitule_poste'])
                    ->sortable(['intitule_poste']),
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
                TextColumn::make('matchings_count')
                    ->label('Candidats proposés')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'info')
                    ->formatStateUsing(fn (int $state): string => $state.' candidat'.($state > 1 ? 's' : ''))
                    ->alignCenter()
                    ->sortable()
                    ->tooltip('Nombre de candidats proposés sur cette offre (plusieurs candidats possibles par offre)'),
                ViewColumn::make('recrutement')
                    ->label('Recrutement')
                    ->view('filament.needs.recrutement'),
                TextColumn::make('date_demarrage')
                    ->label('Démarrage')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('date_cloture')
                    ->label('Clôturé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Clic sur une ligne = ouvre la page de modification de l'offre
            // (description, entreprise, formation, candidats proposés…).
            ->recordUrl(fn (Need $record): string => \App\Filament\Resources\Needs\NeedResource::getUrl('edit', ['record' => $record]))
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
            // Listes déroulantes toujours visibles en barre au-dessus du tableau.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
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
                                    'statut' => MatchingStatut::EnRecherche,
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
            ->emptyStateHeading('Aucune offre proposée')
            ->emptyStateDescription('Enregistrez la première offre d\'une entreprise (poste à pourvoir) : le matching pourra ensuite proposer des candidats compatibles.');
    }

    /** Scope rapide courant lu sur la page (null hors ListNeeds). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Applique un filtre rapide « orienté action » à la requête du tableau.
     * Réutilisé par les compteurs des blocs (ListNeeds) pour rester cohérent.
     *
     *  - a_pourvoir : offres ouvertes (postes encore en recrutement) ;
     *  - sans_candidat : offres ouvertes sans aucun candidat proposé ;
     *  - en_matching : offres ouvertes ayant au moins un candidat proposé.
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_pourvoir' => $query->ouverts(),
            'sans_candidat' => $query->ouverts()->whereDoesntHave('matchings'),
            'en_matching' => $query->ouverts()->whereHas('matchings'),
            default => null,
        };
    }
}
