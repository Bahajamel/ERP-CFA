<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('source')
                    ->label('Origine')
                    ->badge()
                    ->color(fn (string $state) => $state === 'auto' ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state) => $state === 'auto' ? 'Automatique' : 'Manuelle')
                    ->toggleable(),
                TextColumn::make('assignee.name')
                    ->label('Assignée à')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (Task $record) => $record->due_date
                        && $record->due_date->isPast()
                        && ! in_array($record->statut, [TaskStatut::Terminee, TaskStatut::Annulee], true)
                            ? 'danger' : null)
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
                Filter::make('mes_taches')
                    ->label('Mes tâches')
                    ->query(fn (Builder $query) => $query->where('assignee_id', auth()->id())),
                Filter::make('en_retard')
                    ->label('En retard')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', now()->toDateString())
                        ->whereNotIn('statut', [TaskStatut::Terminee->value, TaskStatut::Annulee->value])),
            ])
            ->recordActions([
                Action::make('terminer')
                    ->label('Terminer')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Task $record) => ! in_array($record->statut, [TaskStatut::Terminee, TaskStatut::Annulee], true))
                    ->action(function (Task $record) {
                        $record->update(['statut' => TaskStatut::Terminee->value]);

                        Notification::make()
                            ->title('Tâche terminée')
                            ->success()
                            ->send();
                    }),
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
