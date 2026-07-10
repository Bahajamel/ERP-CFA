<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatut;
use App\Filament\Resources\Admissions\AdmissionActions;
use App\Filament\Resources\Ruptures\RuptureResource;
use App\Models\Admission;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query
                    ->with(['candidate', 'contract.company', 'contract.formation', 'contract.opcoFile'])
                    // Masque les admissions dont le candidat a été supprimé (corbeille).
                    ->whereHas('candidate');
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            ->columns([
                ViewColumn::make('identite')
                    ->label('Apprenti')
                    ->view('filament.admissions.col-identite')
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('contract.formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('contract.opcoFile.statut')
                    ->label('Dossier OPCO')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('validated_at')
                    ->label('Validé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            // Clic sur une ligne = ouvre le panneau « Focus du jour » (la fiche reste
            // accessible via « Aperçu » → « Ouvrir le dossier » ou le menu d'actions).
            ->recordAction('focus')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(AdmissionStatut::class),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(['sm' => 2, 'lg' => 3])
            ->recordActions([
                // Sélectionne l'admission dans le panneau « Focus du jour ».
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Admission $record, $livewire) => $livewire->focusId = $record->getKey()),
                AdmissionActions::valider(),
                ActionGroup::make([
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
                    ->label('Plus')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray'),
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

    /** Scope rapide courant lu sur la page (null hors ListAdmissions). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Filtre rapide sur les admissions (dernière étape du cycle). Le dossier
     * documentaire est toujours complet à ce stade (pièces exigées dès la
     * candidature) : les blocs suivent donc le cycle de validation officielle.
     *
     *  - a_valider : dossier à vérifier puis inscrire officiellement ;
     *  - inscrits : apprenants officiellement inscrits (admission validée) ;
     *  - en_rupture : contrats rompus (dossier de rupture ouvert).
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_valider' => $query->where('statut', AdmissionStatut::AVerifier->value),
            'inscrits' => $query->where('statut', AdmissionStatut::Valide->value),
            'en_rupture' => $query->where('statut', AdmissionStatut::Rupture->value),
            default => null,
        };
    }
}
