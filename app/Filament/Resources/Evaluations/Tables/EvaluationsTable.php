<?php

namespace App\Filament\Resources\Evaluations\Tables;

use App\Enums\EvaluationType;
use App\Models\Evaluation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenant')
                    ->getStateUsing(fn (Evaluation $record): ?string => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('matiere')
                    ->label('Matière')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('note')
                    ->label('Note')
                    ->state(fn (Evaluation $record): string => static::nombre($record->note).' / '.static::nombre($record->bareme))
                    ->badge()
                    ->color(fn (Evaluation $record): string => match (true) {
                        $record->noteSur20() >= 14 => 'success',
                        $record->noteSur20() >= 10 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('coefficient')
                    ->label('Coef.')
                    ->formatStateUsing(fn ($state): string => static::nombre($state))
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('matiere')
                    ->label('Matière')
                    ->options(fn (): array => Evaluation::query()
                        ->whereNotNull('matiere')
                        ->distinct()
                        ->orderBy('matiere')
                        ->pluck('matiere', 'matiere')
                        ->all())
                    ->searchable(),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(EvaluationType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    /** Affiche un nombre sans décimales inutiles (16, 15,5…). */
    protected static function nombre(mixed $valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ''), '0'), ',');
    }
}
