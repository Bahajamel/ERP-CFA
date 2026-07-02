<?php

namespace App\Filament\Widgets;

use App\Models\Interaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard commercial (P0-12-2) : les relances à faire du commercial connecté
 * — interactions dont la prochaine action datée est échue, la plus urgente en tête.
 */
class RelancesCommercialesTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Mes relances à faire';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Commercial', 'Administrateur']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Interaction::query()
                    ->with('interactable')
                    ->relanceDue()
                    ->where('user_id', Auth::id())
                    ->orderBy('prochaine_action_le')
            )
            ->emptyStateHeading('Aucune relance en retard')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('prochaine_action_le')
                    ->label('À faire le')
                    ->date('d/m/Y')
                    ->color('danger'),
                TextColumn::make('interactable')
                    ->label('Dossier')
                    ->state(fn (Interaction $record): string => $record->interactable?->raison_sociale
                        ?? $record->interactable?->nom_complet
                        ?? '—'),
                TextColumn::make('type')
                    ->label('Canal')
                    ->badge(),
                TextColumn::make('prochaine_action')
                    ->label('Action à mener')
                    ->wrap(),
            ]);
    }
}
