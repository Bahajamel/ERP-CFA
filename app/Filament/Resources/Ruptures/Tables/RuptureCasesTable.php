<?php

namespace App\Filament\Resources\Ruptures\Tables;

use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\RuptureCaseActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RuptureCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract.candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->contract?->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(),
                TextColumn::make('contract.company.raison_sociale')
                    ->label('Entreprise')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('date_rupture')
                    ->label('Rupture le')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('motif')
                    ->label('Motif')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('initiateur')
                    ->label('Initiative')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('recherche_employeur')
                    ->label('Reclassement')
                    ->boolean()
                    ->tooltip(fn ($record) => $record->nouvelleCompany?->raison_sociale
                        ? 'Reclassé chez '.$record->nouvelleCompany->raison_sociale
                        : ($record->recherche_employeur ? 'Recherche en cours' : null))
                    ->toggleable(),
                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut du dossier')
                    ->options(RuptureStatut::class),
                SelectFilter::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                RuptureCaseActions::changerStatut(),
                RuptureCaseActions::reclasser(),
                RuptureCaseActions::clore(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('date_rupture', 'desc');
    }
}
