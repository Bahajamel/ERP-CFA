<?php

namespace App\Filament\Resources\Promotions\RelationManagers;

use App\Filament\Actions\FicheApprenant;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
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
                    ->searchable(['nom', 'prenom'])
                    ->weight('bold')
                    ->color('primary')
                    ->tooltip('Voir la fiche apprenant')
                    ->action(FicheApprenant::action()),
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
                AttachAction::make()
                    ->label('Rattacher un apprenti')
                    ->recordSelectSearchColumns(['nom', 'prenom', 'email']),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Retirer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
