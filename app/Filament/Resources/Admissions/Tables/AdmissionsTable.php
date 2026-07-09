<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatut;
use App\Filament\Resources\Admissions\AdmissionActions;
use App\Filament\Resources\Ruptures\RuptureResource;
use App\Models\Admission;
use Filament\Actions\Action;
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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['candidate.media', 'contract.company', 'contract.opcoFile'])
                // Masque les admissions dont le candidat a été supprimé (corbeille).
                ->whereHas('candidate'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('contract.company.raison_sociale')
                    ->label('Entreprise')
                    ->placeholder('—'),
                TextColumn::make('contract.opcoFile.statut')
                    ->label('Dossier OPCO')
                    ->badge()
                    ->placeholder('—'),
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
                AdmissionActions::declarerRupture(),
                // Lien vers le dossier lié créé automatiquement (cycle apprenant).
                Action::make('voirRupture')
                    ->label('Voir la rupture')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('danger')
                    ->visible(fn (Admission $record): bool => $record->statut === AdmissionStatut::Rupture
                        && $record->contract?->rupture !== null)
                    ->url(fn (Admission $record): string => RuptureResource::getUrl(
                        'edit',
                        ['record' => $record->contract->rupture],
                    )),
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
            ->emptyStateHeading('Aucune admission officielle')
            ->emptyStateDescription('Une admission apparaît automatiquement dès que le dossier OPCO d\'un contrat '
                .'signé est accepté par l\'OPCO. Il ne reste alors qu\'à la valider pour inscrire l\'apprenant.');
    }
}
