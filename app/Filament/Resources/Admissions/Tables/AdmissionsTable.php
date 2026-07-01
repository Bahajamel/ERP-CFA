<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatut;
use App\Enums\ChecklistItemStatut;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('conformite')
                    ->label('Pièces obligatoires')
                    ->state(fn (Admission $record) => self::conformiteLabel($record))
                    ->badge()
                    ->color(fn (string $state) => $state === 'Complet' ? 'success' : 'warning'),
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
                AdmissionActions::changerStatut(),
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

    private static function conformiteLabel(Admission $record): string
    {
        $manquantes = $record->items
            ->where('est_obligatoire', true)
            ->filter(fn ($item) => $item->statut !== ChecklistItemStatut::Presente)
            ->count();

        return $manquantes === 0 ? 'Complet' : $manquantes.' manquante(s)';
    }
}
