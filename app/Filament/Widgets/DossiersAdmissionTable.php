<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Dashboard admission / administratif (P0-12-3) : la file des dossiers d'admission
 * à traiter, avec le nombre de pièces obligatoires manquantes. Ligne cliquable.
 */
class DossiersAdmissionTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Admissions à finaliser';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admission', 'Administratif', 'Administrateur']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Admission::query()
                    ->with('candidate')
                    ->whereIn('statut', [
                        AdmissionStatut::AVerifier->value,
                        AdmissionStatut::Incomplet->value,
                        AdmissionStatut::NonConforme->value,
                    ])
                    ->latest()
            )
            ->recordUrl(fn (Admission $record): string => AdmissionResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Aucune admission à traiter')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn (Admission $record): ?string => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom']),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('pieces_manquantes')
                    ->label('Pièces manquantes')
                    ->state(fn (Admission $record): int => $record->piecesObligatoiresManquantes()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ]);
    }
}
