<?php

namespace App\Filament\Resources\Seances\Tables;

use App\Enums\SeanceStatut;
use App\Models\Seance;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SeancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('libelle')
                    ->label('Matière')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('formateur.name')
                    ->label('Formateur')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('taux_presence')
                    ->label('Assiduité')
                    ->state(fn (Seance $record): string => ($t = $record->tauxPresence()) === null ? '—' : "{$t} %")
                    ->badge()
                    ->color(fn (Seance $record): string => match (true) {
                        $record->tauxPresence() === null => 'gray',
                        $record->tauxPresence() >= 90 => 'success',
                        $record->tauxPresence() >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class),
            ])
            ->recordActions([
                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Seance $record): bool => $record->statut !== SeanceStatut::Validee)
                    ->requiresConfirmation()
                    ->modalDescription('Valider la séance ? Toutes les présences doivent être renseignées.')
                    ->action(function (Seance $record): void {
                        try {
                            $record->update(['statut' => SeanceStatut::Validee]);
                            Notification::make()->success()->title('Séance validée')->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Validation impossible')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
