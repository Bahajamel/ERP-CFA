<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatut;
use App\Models\Company;
use App\Models\Formation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query->with(['opco', 'contacts', 'needs', 'interactions']);
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            ->columns([
                ViewColumn::make('identite')
                    ->label('Entreprise')
                    ->view('filament.companies.col-identite')
                    ->searchable(['raison_sociale', 'nom_commercial'])
                    ->sortable(['raison_sociale']),
                TextColumn::make('contact')
                    ->label('Contact principal')
                    ->state(fn (Company $record): ?string => $record->contactPrincipal->first()?->nom
                        ?? $record->contacts->first()?->nom)
                    ->description(fn (Company $record): ?string => $record->contactPrincipal->first()?->telephone
                        ?? $record->contacts->first()?->telephone)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('secteur')
                    ->label('Secteur')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('besoins_ouverts')
                    ->label('Besoins ouverts')
                    ->badge()
                    ->color(fn (Company $record): string => $record->besoinsOuvertsCount() > 0 ? 'warning' : 'gray')
                    ->state(fn (Company $record): string => $record->besoinsOuvertsCount().' poste'
                        .($record->besoinsOuvertsCount() > 1 ? 's' : '')),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('derniere_activite')
                    ->label('Dernière activité')
                    ->state(fn (Company $record): ?string => $record->derniereActivite()['label'])
                    ->description(fn (Company $record): ?string => $record->derniereActivite()['quand'])
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            // Clic sur une ligne = ouvre le panneau « Focus entreprise » (et non la
            // fiche : elle reste accessible via « Aperçu » ou le menu « Plus »).
            ->recordAction('focus')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CompanyStatut::class),
                SelectFilter::make('secteur')
                    ->label('Secteur')
                    ->options(fn (): array => Company::query()
                        ->whereNotNull('secteur')
                        ->distinct()
                        ->orderBy('secteur')
                        ->pluck('secteur', 'secteur')
                        ->all()),
                SelectFilter::make('opco_id')
                    ->label('OPCO')
                    ->relationship('opco', 'nom'),
                SelectFilter::make('formation_recherchee')
                    ->label('Formation recherchée')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $q, $formationId): Builder => $q->whereHas(
                            'needs',
                            fn (Builder $n): Builder => $n->ouverts()->where('formation_id', $formationId),
                        ),
                    )),
                Filter::make('relance_a_faire')
                    ->label('Relance à faire')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'interactions',
                        fn (Builder $q): Builder => $q->relanceDue(),
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Company $record, $livewire) => $livewire->focusId = $record->getKey()),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])
                    ->label('Plus')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('raison_sociale')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('Aucune entreprise enregistrée')
            ->emptyStateDescription('Ajoutez une entreprise partenaire ou lancez une prospection La Bonne Alternance depuis les besoins pour alimenter votre CRM.');
    }

    /** Scope rapide courant lu sur la page (null hors ListCompanies). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Filtre rapide « orienté action » sur les entreprises. Réutilisé par les
     * compteurs des blocs (ListCompanies) pour rester cohérent.
     *
     *  - a_relancer : aucune interaction depuis plus de 7 jours (sans activité récente) ;
     *  - besoins_ouverts : au moins un besoin ouvert (à pourvoir) ;
     *  - prospects : entreprises encore au statut Prospect (à convertir).
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_relancer' => $query->whereDoesntHave(
                'interactions',
                fn (Builder $q) => $q->where('date_interaction', '>=', now()->subDays(7)),
            ),
            'besoins_ouverts' => $query->whereHas('needs', fn (Builder $q) => $q->ouverts()),
            'prospects' => $query->where('statut', CompanyStatut::Prospect->value),
            default => null,
        };
    }
}
