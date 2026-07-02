<?php

namespace App\Filament\Resources\Seances\Tables;

use App\Enums\SeanceStatut;
use App\Models\Seance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('libelle')
                    ->label('Matière')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('formateur.name')
                    ->label('Formateur')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('taux_presence')
                    ->label('Assiduité')
                    ->state(fn (Seance $record): string => ($t = $record->tauxPresence()) === null ? '—' : "{$t} %")
                    ->badge()
                    ->color(fn (Seance $record): string => match (true) {
                        $record->tauxPresence() === null => 'gray',
                        $record->tauxPresence() >= 90 => 'success',
                        $record->tauxPresence() >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
