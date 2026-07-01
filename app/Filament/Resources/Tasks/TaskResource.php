<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_tasks') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|\UnitEnum|null $navigationGroup = 'Pilotage';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tâches & alertes';

    protected static ?string $modelLabel = 'tâche';

    protected static ?string $pluralModelLabel = 'tâches';

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    /** Badge de navigation : mes tâches ouvertes (à faire / en cours / en retard). */
    public static function getNavigationBadge(): ?string
    {
        $count = Task::query()
            ->where('assignee_id', auth()->id())
            ->whereIn('statut', [
                \App\Enums\TaskStatut::AFaire->value,
                \App\Enums\TaskStatut::EnCours->value,
                \App\Enums\TaskStatut::EnRetard->value,
            ])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $enRetard = Task::query()
            ->where('assignee_id', auth()->id())
            ->where('statut', \App\Enums\TaskStatut::EnRetard->value)
            ->exists();

        return $enRetard ? 'danger' : 'warning';
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
