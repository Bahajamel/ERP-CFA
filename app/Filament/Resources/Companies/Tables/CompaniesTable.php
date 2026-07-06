<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatut;
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
