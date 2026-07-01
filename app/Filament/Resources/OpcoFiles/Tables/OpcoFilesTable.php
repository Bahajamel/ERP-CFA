<?php

namespace App\Filament\Resources\OpcoFiles\Tables;

use App\Enums\OpcoStatut;
use App\Filament\Resources\OpcoFiles\OpcoFileActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpcoFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract.candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->contract?->candidate?->nom_complet)
                    ->description(fn ($record) => $record->contract?->company?->raison_sociale)
                    ->searchable(),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('montant_prevu')
                    ->label('Montant prévu')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('montant_accepte')
                    ->label('Montant accepté')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('date_depot')
                    ->label('Déposé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(OpcoStatut::class),
                Filter::make('bloques')
                    ->label('Dossiers bloqués')
                    ->query(fn (Builder $query) => $query->whereIn('statut', OpcoStatut::bloques())),
            ])
            ->recordActions([
                OpcoFileActions::preparerDepot(),
                OpcoFileActions::accepter(),
                OpcoFileActions::rejeter(),
                OpcoFileActions::changerStatut(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
