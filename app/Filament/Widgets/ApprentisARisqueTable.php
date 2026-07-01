<?php

namespace App\Filament\Widgets;

use App\Enums\RiskLevel;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Apprentis présentant un risque élevé ou critique de rupture de contrat.
 * Le levier concurrentiel : anticiper la rupture au lieu de la constater.
 * Réservé à la Direction, la Pédagogie et l'Administrateur.
 */
class ApprentisARisqueTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Apprentis à risque de rupture';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur', 'Pédagogie']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Contract::query()
                    ->with(['candidate', 'company'])
                    ->whereIn('risk_level', RiskLevel::aRisque())
                    ->orderByDesc('risk_score')
            )
            ->emptyStateHeading('Aucun apprenti à risque élevé')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn (Contract $record) => $record->candidate?->nom_complet)
                    ->description(fn (Contract $record) => $record->company?->raison_sociale)
                    ->url(fn (Contract $record) => ContractResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('risk_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (Contract $record) => $record->risk_level?->getColor() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state.'/100'),
                TextColumn::make('risk_level')
                    ->label('Niveau')
                    ->badge(),
                TextColumn::make('risk_factors')
                    ->label('Facteurs déclencheurs')
                    ->getStateUsing(fn (Contract $record) => collect($record->risk_factors ?? [])
                        ->pluck('label')
                        ->implode(' · '))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('risk_evaluated_at')
                    ->label('Évalué le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ]);
    }
}
