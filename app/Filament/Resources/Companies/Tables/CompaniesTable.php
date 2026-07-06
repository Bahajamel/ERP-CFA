<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatut;
use App\Models\Company;
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
                TextColumn::make('formations_recherchees')
                    ->label('Formations recherchées')
                    ->state(fn (Company $record): array => $record->formationsRecherchees()->all())
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->listWithLineBreaks()
                    ->tooltip('Formations des besoins ouverts de cette entreprise'),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->badge()
                    ->color('info'),
                TextColumn::make('satisfaction')
                    ->label('Satisfaction')
                    ->state(fn (Company $record): ?int => $record->derniereSatisfaction())
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}/5" : '—')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(),
                TextColumn::make('incidents_count')
                    ->label('Incidents')
                    ->counts('incidents')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->toggleable(),
                TextColumn::make('prochaine_relance')
                    ->label('Prochaine relance')
                    ->state(fn (Company $record): ?string => $record->prochaineRelance()?->prochaine_action_le?->format('d/m/Y'))
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (Company $record): string => ($relance = $record->prochaineRelance())
                        && $relance->prochaine_action_le->isPast()
                        ? 'danger'
                        : 'gray')
                    ->toggleable(),
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
            ->defaultSort('raison_sociale');
    }
}
