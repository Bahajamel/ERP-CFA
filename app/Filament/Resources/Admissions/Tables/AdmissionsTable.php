<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdmissionsTable
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
                TextColumn::make('items_count')
                    ->label('Pièces')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('validatedBy.name')
                    ->label('Validé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('validated_at')
                    ->label('Validé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(AdmissionStatut::class),
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
