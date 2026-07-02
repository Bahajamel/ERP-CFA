<?php

namespace App\Filament\Resources\CfaMissions\Tables;

use App\Models\CfaMission;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CfaMissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('documents'))
            ->defaultSort('numero')
            ->columns([
                TextColumn::make('numero')
                    ->label('N°')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state.'°')
                    ->sortable(),
                TextColumn::make('titre')
                    ->label('Mission')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (CfaMission $record) => str($record->texte)->limit(120))
                    ->wrap(),
                TextColumn::make('documents_count')
                    ->label('Livrables')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('couverture')
                    ->label('Couverture')
                    ->badge()
                    ->state(fn (CfaMission $record) => $record->documents_count > 0 ? 'Couverte' : 'Non couverte')
                    ->color(fn (CfaMission $record) => $record->documents_count > 0 ? 'success' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('couverte')
                    ->label('Couverture')
                    ->placeholder('Toutes les missions')
                    ->trueLabel('Couvertes uniquement')
                    ->falseLabel('Non couvertes uniquement')
                    ->queries(
                        true: fn (Builder $query) => $query->has('documents'),
                        false: fn (Builder $query) => $query->doesntHave('documents'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('texte')
                    ->label('Texte officiel')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(fn (CfaMission $record) => $record->numero.'° — '.$record->titre)
                    ->modalDescription(fn (CfaMission $record) => $record->texte)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ]);
    }
}
