<?php

namespace App\Filament\Resources\Ruptures\Tables;

use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\Schemas\RuptureForm;
use App\Models\Rupture;
use App\StateMachine\HasStateTransitions;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
                    ->label('Contrat')
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
                    ->label('Accompagnement')
                    ->badge(),
                TextColumn::make('nouvel_employeur')
                    ->label('Reclassement')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Ouverte le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Accompagnement')
                    ->options(RuptureStatut::class),
                SelectFilter::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class),
            ])
            ->recordActions([
                Action::make('faireEvoluer')
                    ->label('Faire évoluer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Rupture $record): bool => $record->allowedTransitions() !== [])
                    ->schema([
                        Select::make('statut')
                            ->label('Nouveau statut')
                            ->options(fn (Rupture $record): array => collect($record->allowedTransitions())
                                ->mapWithKeys(fn (HasStateTransitions $s): array => [$s->value => $s->getLabel()])
                                ->all())
                            ->required(),
                    ])
                    ->action(function (Rupture $record, array $data): void {
                        try {
                            $record->transitionTo(RuptureStatut::from($data['statut']));
                            Notification::make()->success()->title('Accompagnement mis à jour')->send();
                        } catch (InvalidTransitionException $e) {
                            Notification::make()->danger()->title('Transition impossible')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
