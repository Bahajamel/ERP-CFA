<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')
                    ->label('Titre')
                    ->description(fn ($record) => $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('assignee.name')
                    ->label('Assignée à')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('priorite')
                    ->label('Priorité')
                    ->badge(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(TaskStatut::class),
                SelectFilter::make('priorite')
                    ->label('Priorité')
                    ->options(TaskPriorite::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('due_date', 'asc');
    }
}
