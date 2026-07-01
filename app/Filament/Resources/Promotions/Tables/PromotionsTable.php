<?php

namespace App\Filament\Resources\Promotions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')
                    ->label('Classe')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('annee_scolaire')
                    ->label('Année scolaire')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('apprentis_count')
                    ->label('Apprentis')
                    ->counts('apprentis')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('date_debut')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('annee_scolaire', 'desc');
    }
}
