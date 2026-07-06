<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatut;
use App\Filament\Resources\Admissions\AdmissionActions;
use App\Models\Admission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('candidate.media'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('cv')
                    ->label('CV')
                    ->state(fn (Admission $record) => $record->cvManquant() ? 'CV manquant' : 'CV fourni')
                    ->badge()
                    ->color(fn (string $state) => $state === 'CV fourni' ? 'success' : 'danger'),
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
                AdmissionActions::valider(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading('Aucun dossier de pré-admission')
            ->emptyStateDescription('Les dossiers sont ouverts automatiquement à la création d\'un candidat. Créez un candidat pour commencer.');
    }
}
