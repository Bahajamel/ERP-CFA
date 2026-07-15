<?php

namespace App\Filament\Resources\Needs\Tables;

use App\Enums\NeedStatut;
use App\Models\Need;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
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
            // Clic sur une ligne = ouvre le panneau « Focus offre » (la page de
            // modification reste accessible via « Aperçu » → « Ouvrir/modifier »
            // ou le menu d'actions).
            ->recordAction('focus')
            ->recordUrl(null)
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
            // Filtres instantanés (sans bouton « Appliquer ») pour une barre compacte.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->recordActions([
                // Sélectionne l'offre dans le panneau « Focus offre » (clic sur la
                // ligne = même action, sans navigation).
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Need $record, $livewire) => $livewire->focusId = $record->getKey()),
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
                // Formulaire « Proposer des candidats » : modale riche (composant Livewire dédié)
                // — sélection multiple, score, canal, relance, message, tâche de relance.
                Action::make('proposerCandidats')
                    ->label('Proposer des candidats')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->modalHeading('Proposer des candidats')
                    ->modalDescription('Sélectionnez les profils à proposer à l\'entreprise et préparez le suivi commercial.')
                    ->modalWidth('7xl')
                    ->modalContent(fn (Need $record): View => view('filament.matching.proposer-host', ['need' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
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
