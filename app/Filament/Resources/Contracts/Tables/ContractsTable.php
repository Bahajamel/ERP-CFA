<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('company.raison_sociale')
                    ->label('Entreprise')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('date_debut')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut_signature')
                    ->label('Signature')
                    ->badge(),
                TextColumn::make('statut_contrat')
                    ->label('Statut contrat')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut_contrat')
                    ->label('Statut du contrat')
                    ->options(ContractStatut::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ContractActions::signer(),
                ContractActions::envoyerSignature(),
                ContractActions::simulerSignature(),
                ContractActions::changerStatut(),
                ContractActions::genererLivrables(),
                ContractActions::importerLivrables(),
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
            ->defaultSort('created_at', 'desc');
    }
}
