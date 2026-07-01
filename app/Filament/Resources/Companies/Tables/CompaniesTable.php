<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Enums\CompanyStatut;
use App\Models\Company;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CompanyStatut::class),
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
