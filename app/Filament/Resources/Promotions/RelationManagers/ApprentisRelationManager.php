<?php

namespace App\Filament\Resources\Promotions\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprentisRelationManager extends RelationManager
{
    protected static string $relationship = 'apprentis';

    protected static ?string $title = 'Apprentis de la classe';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom')
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->nom_complet)
                    ->searchable(['nom', 'prenom']),
                TextColumn::make('formationVisee.libelle')
                    ->label('Formation visée')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('Rattacher un apprenti')
                    ->recordSelectSearchColumns(['nom', 'prenom', 'email']),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label('Retirer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ]);
    }
}
