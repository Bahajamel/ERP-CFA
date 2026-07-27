<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatut;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TachesPrioritairesTable extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Tâches prioritaires';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
                    ->with('assignee')
                    ->whereIn('statut', [
                        TaskStatut::AFaire->value,
                        TaskStatut::EnCours->value,
                        TaskStatut::EnAttente->value,
                        TaskStatut::EnRetard->value,
                    ])
                    ->orderByRaw("CASE priorite WHEN 'urgente' THEN 0 WHEN 'haute' THEN 1 WHEN 'normale' THEN 2 ELSE 3 END")
                    ->orderBy('due_date')
            )
            ->emptyStateHeading('Aucune tâche en attente')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('titre')
                    ->label('Tâche')
                    ->wrap(),
                TextColumn::make('assignee.name')
                    ->label('Assignée à')
                    ->placeholder('—'),
                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('priorite')
                    ->label('Priorité')
                    ->badge(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ]);
    }
}
