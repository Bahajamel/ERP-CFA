<?php

namespace App\Filament\Resources\ServiceFaits\Tables;

use App\Models\ServiceFait;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceFaitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('periode')
                    ->label('Période')
                    ->state(fn (ServiceFait $record): string => $record->periodeLibelle())
                    ->sortable(['annee', 'mois']),
                TextColumn::make('nb_seances')
                    ->label('Séances')
                    ->alignCenter(),
                TextColumn::make('nb_heures')
                    ->label('Heures')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 1, ',', ' '), '0'), ',').' h')
                    ->alignCenter(),
                TextColumn::make('taux_presence')
                    ->label('Assiduité')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state} %")
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('validatedBy.name')
                    ->label('Validé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('validated_at')
                    ->label('Validé le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('validated_at', 'desc');
    }
}
