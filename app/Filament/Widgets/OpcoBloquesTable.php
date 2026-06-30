<?php

namespace App\Filament\Widgets;

use App\Enums\OpcoStatut;
use App\Models\OpcoFile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpcoBloquesTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Dossiers OPCO bloqués';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OpcoFile::query()
                    ->with(['contract.candidate', 'contract.company', 'opco', 'responsableCorrection'])
                    ->whereIn('statut', OpcoStatut::bloques())
            )
            ->emptyStateHeading('Aucun dossier OPCO bloqué')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('contract.candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->contract?->candidate?->nom_complet)
                    ->description(fn ($record) => $record->contract?->company?->raison_sociale),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('motif_rejet')
                    ->label('Motif')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('responsableCorrection.name')
                    ->label('Responsable')
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ]);
    }
}
