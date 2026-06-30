<?php

namespace App\Filament\Resources\Formations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code_rncp')
                    ->label('Code RNCP')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('niveau')
                    ->label('Niveau')
                    ->placeholder('—'),
                TextColumn::make('duree_mois')
                    ->label('Durée (mois)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rythme_defaut')
                    ->label('Rythme')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('libelle');
    }
}
