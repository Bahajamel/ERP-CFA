<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tâche')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titre')
                            ->label('Titre')
                            ->placeholder("ex : Relancer l'entreprise pour la signature")
                            ->required()
                            ->columnSpanFull(),
                        Select::make('assignee_id')
                            ->label('Assignée à')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('due_date')
                            ->label('Échéance')
                            ->displayFormat('d/m/Y'),
                        Select::make('priorite')
                            ->label('Priorité')
                            ->options(TaskPriorite::class)
                            ->default(TaskPriorite::Normale->value)
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(TaskStatut::class)
                            ->default(TaskStatut::AFaire->value)
                            ->required(),
                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('ex : Contexte, détails, prochaine action à mener…')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
