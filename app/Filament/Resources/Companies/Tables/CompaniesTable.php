<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatut;
use App\Models\Formation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
            ->columns([
                TextColumn::make('raison_sociale')
                    ->label('Raison sociale')
                    ->description(fn ($record) => $record->nom_commercial)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('siret')
                    ->label('SIRET')
                    ->searchable()
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
                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->badge()
                    ->color('info'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CompanyStatut::class),
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
            ->defaultSort('raison_sociale')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('Aucune entreprise enregistrée')
            ->emptyStateDescription('Ajoutez une entreprise partenaire ou lancez une prospection La Bonne Alternance depuis les besoins pour alimenter votre CRM.');
    }
}
