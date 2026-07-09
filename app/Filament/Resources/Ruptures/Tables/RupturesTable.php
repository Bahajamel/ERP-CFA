<?php

namespace App\Filament\Resources\Ruptures\Tables;

use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\Schemas\RuptureForm;
use App\Models\Rupture;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RupturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract')
                    ->label('Apprenant — entreprise')
                    ->state(fn (Rupture $record): string => $record->contract
                        ? RuptureForm::libelleContrat($record->contract)
                        : '—')
                    ->searchable(false)
                    ->wrap(),
                TextColumn::make('date_rupture')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('motif')
                    ->label('Motif')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('initiative')
                    ->label('Initiative')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Ouverte le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(RuptureStatut::class),
                SelectFilter::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class),
            ])
            ->recordActions([
                self::cloturer(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-scissors')
            ->emptyStateHeading('Aucun dossier de rupture')
            ->emptyStateDescription('Une rupture déclarée depuis une admission (ou ouverte ici sur un contrat) '
                .'apparaît « À traiter » : les documents de l\'apprenti restent accessibles dans sa fiche, '
                .'puis clôturez le dossier.');
    }

    private static function cloturer(): Action
    {
        return Action::make('cloturer')
            ->label('Clôturer')
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->visible(fn (Rupture $record): bool => $record->currentState()->canTransitionTo(RuptureStatut::Cloturee))
            ->requiresConfirmation()
            ->modalDescription('Clôturer ce dossier de rupture ? Cette action marque la fin du traitement administratif.')
            ->action(function (Rupture $record): void {
                try {
                    $record->transitionTo(RuptureStatut::Cloturee);
                    Notification::make()->success()->title('Dossier de rupture clôturé')->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Transition impossible')->body($e->getMessage())->send();
                }
            });
    }
}
