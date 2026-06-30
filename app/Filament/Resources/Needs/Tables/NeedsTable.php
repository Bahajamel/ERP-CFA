<?php

namespace App\Filament\Resources\Needs\Tables;

use App\Enums\NeedStatut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('intitule_poste')
                    ->label('Poste')
                    ->description(fn ($record) => $record->company?->raison_sociale)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('localisation')
                    ->label('Lieu')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('nb_postes')
                    ->label('Postes')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('matchings_count')
                    ->label('Candidats proposés')
                    ->counts('matchings')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('date_demarrage')
                    ->label('Démarrage')
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
                    ->options(NeedStatut::class),
                SelectFilter::make('company_id')
                    ->label('Entreprise')
                    ->relationship('company', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
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
