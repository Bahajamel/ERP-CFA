<?php

namespace App\Filament\Editeur\Resources\Organisations\Tables;

use App\Models\Organisation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrganisationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['users', 'candidates']))
            ->columns([
                TextColumn::make('nom')
                    ->label('CFA')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Organisation $record): string => '/admin/'.$record->slug),

                TextColumn::make('users_count')
                    ->label('Membres')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('candidates_count')
                    ->label('Candidats')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('actif')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Actif' : 'Suspendu')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('Client depuis')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('actif')
                    ->label('CFA actif'),
            ])
            ->recordActions([
                EditAction::make(),

                // Suspension réversible : on ne supprime jamais un CFA depuis l'UI
                // (ses données — dont des pièces à NIR — partiraient avec lui).
                Action::make('basculerActivation')
                    ->label(fn (Organisation $record): string => $record->actif ? 'Suspendre' : 'Réactiver')
                    ->icon(fn (Organisation $record): string => $record->actif ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle')
                    ->color(fn (Organisation $record): string => $record->actif ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Organisation $record): string => $record->actif
                        ? "Suspendre {$record->nom} ?"
                        : "Réactiver {$record->nom} ?")
                    ->modalDescription(fn (Organisation $record): string => $record->actif
                        ? 'Ses membres ne pourront plus s\'y connecter. Les données sont conservées et l\'accès est rétablissable à tout moment.'
                        : 'Ses membres retrouveront l\'accès à leur espace.')
                    ->action(function (Organisation $record): void {
                        $record->update(['actif' => ! $record->actif]);

                        Notification::make()
                            ->title($record->actif ? "{$record->nom} réactivé" : "{$record->nom} suspendu")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('nom')
            ->emptyStateHeading('Aucun CFA')
            ->emptyStateDescription('Créez un CFA pour lui ouvrir son espace de travail.');
    }
}
