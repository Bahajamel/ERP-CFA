<?php

namespace App\Filament\Resources\FinanceLines\Tables;

use App\Filament\Resources\FinanceLines\Schemas\FinanceLineForm;
use App\Models\FinanceLine;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceLinesTable
{
    /** Formatage monétaire « 7 200,00 € ». */
    public static function euros(float|string|null $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ').' €';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract')
                    ->label('Contrat')
                    ->state(fn (FinanceLine $record): string => $record->contract
                        ? FinanceLineForm::libelleContrat($record->contract)
                        : '—')
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'contract.candidate',
                        fn (Builder $q) => $q->where('nom', 'like', "%{$search}%")->orWhere('prenom', 'like', "%{$search}%"),
                    )),
                TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('montant_attendu')
                    ->label('Attendu')
                    ->formatStateUsing(fn ($state): string => self::euros($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('facture')
                    ->label('Facturé')
                    ->state(fn (FinanceLine $record): string => self::euros($record->montantFacture()))
                    ->alignEnd(),
                TextColumn::make('encaisse')
                    ->label('Encaissé')
                    ->state(fn (FinanceLine $record): string => self::euros($record->montantEncaisse()))
                    ->color('success')
                    ->alignEnd(),
                TextColumn::make('reste')
                    ->label('Reste à encaisser')
                    ->state(fn (FinanceLine $record): string => self::euros($record->resteAEncaisser()))
                    ->badge()
                    ->color(fn (FinanceLine $record): string => $record->resteAEncaisser() > 0 ? 'warning' : 'success')
                    ->alignEnd(),
                TextColumn::make('montant_bloque')
                    ->label('Bloqué')
                    ->formatStateUsing(fn ($state): string => self::euros($state))
                    ->badge()
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'gray')
                    ->tooltip(fn (FinanceLine $record): ?string => $record->motif_blocage)
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('contract_id')
                    ->label('Contrat')
                    ->relationship('contract', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => FinanceLineForm::libelleContrat($record))
                    ->searchable()
                    ->preload(),
                Filter::make('bloque')
                    ->label('Montant bloqué')
                    ->query(fn (Builder $query): Builder => $query->where('montant_bloque', '>', 0)),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
