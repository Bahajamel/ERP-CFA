<?php

namespace App\Filament\Resources\Matchings\Tables;

use App\Enums\MatchingStatut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MatchingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('need.intitule_poste')
                    ->label('Besoin')
                    ->description(fn ($record) => $record->need?->company?->raison_sociale)
                    ->searchable(),
                IconColumn::make('cv_envoye')
                    ->label('CV envoyé')
                    ->boolean(),
                TextColumn::make('date_entretien')
                    ->label('Entretien')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(MatchingStatut::class),
            ])
            ->recordActions([
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
