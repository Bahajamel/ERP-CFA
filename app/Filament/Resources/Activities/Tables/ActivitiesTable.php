<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Module')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Action')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => 'Création',
                        'updated' => 'Modification',
                        'deleted' => 'Suppression',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Objet')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')
                    ->label('#')
                    ->placeholder('—'),
                TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->placeholder('Système'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Module')
                    ->options([
                        'candidat' => 'Candidat',
                        'contrat' => 'Contrat',
                        'opco' => 'OPCO',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
