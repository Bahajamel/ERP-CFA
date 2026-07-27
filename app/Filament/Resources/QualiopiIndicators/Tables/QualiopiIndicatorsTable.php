<?php

namespace App\Filament\Resources\QualiopiIndicators\Tables;

use App\Enums\QualiopiStatut;
use App\Support\QualiopiCriteres;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class QualiopiIndicatorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')
                    ->label('N°')
                    ->sortable(),
                TextColumn::make('libelle')
                    ->label('Exigence')
                    ->wrap()
                    ->searchable()
                    ->limit(90),
                IconColumn::make('specifique_cfa')
                    ->label('CFA')
                    ->boolean()
                    ->tooltip('Obligation spécifique aux CFA'),
                TextColumn::make('statut')
                    ->label('Conformité')
                    ->badge(),
                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('documents_count')
                    ->label('Preuves')
                    ->counts('documents')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('reviewed_at')
                    ->label('Dernière revue')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->groups([
                Group::make('critere')
                    ->label('Critère')
                    ->getTitleFromRecordUsing(fn ($record) => 'Critère '.$record->critere.' — '.QualiopiCriteres::label((int) $record->critere)),
            ])
            ->defaultGroup('critere')
            ->filters([
                SelectFilter::make('statut')
                    ->label('Conformité')
                    ->options(QualiopiStatut::class),
                SelectFilter::make('critere')
                    ->label('Critère')
                    ->options(QualiopiCriteres::LABELS),
                TernaryFilter::make('specifique_cfa')
                    ->label('Spécifique CFA'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('numero')
            ->paginated([25, 50, 'all']);
    }
}
