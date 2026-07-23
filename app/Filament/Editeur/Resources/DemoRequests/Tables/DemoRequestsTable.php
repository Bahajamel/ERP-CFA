<?php

namespace App\Filament\Editeur\Resources\DemoRequests\Tables;

use App\Enums\DemoRequestStatut;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DemoRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization_name')
                    ->label('Établissement')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->nomComplet()
                        .($record->job_title ? ' — '.$record->job_title : '')),
                TextColumn::make('email')
                    ->label('Contact')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Adresse copiée')
                    ->description(fn ($record): ?string => $record->phone),
                TextColumn::make('learner_count')
                    ->label('Apprenants')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('main_need')
                    ->label('Besoin principal')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(DemoRequestStatut::class),
            ])
            ->recordActions([
                EditAction::make()->label('Traiter'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-presentation-chart-line')
            ->emptyStateHeading('Aucune demande de démonstration')
            ->emptyStateDescription('Les demandes déposées depuis le site vitrine apparaîtront ici.');
    }
}
