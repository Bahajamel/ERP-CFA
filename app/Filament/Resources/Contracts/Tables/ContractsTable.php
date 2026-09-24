<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractActions;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Masque les contrats dont le candidat a été supprimé (corbeille).
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('candidate'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('company.raison_sociale')
                    ->label('Entreprise')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('date_debut')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut_signature')
                    ->label('Signature')
                    ->badge(),
                TextColumn::make('statut_contrat')
                    ->label('Statut contrat')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut_contrat')
                    ->label('Statut du contrat')
                    ->options(ContractStatut::class),
                TrashedFilter::make(),
            ])
            // Listes déroulantes toujours visibles en barre au-dessus du tableau
            // (au lieu du menu déroulant « Filtres »), comme Candidats, Entreprises
            // et Entretiens. Filtres instantanés, sans bouton « Appliquer ».
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 2,
            ])
            ->recordActions([
                // Actions principales visibles ; le reste dans un menu « ⋮ »
                // pour garder la ligne lisible (plus de débordement horizontal).
                // Pas de ViewAction : la ressource n'a pas de page « view » (le
                // dossier = la page d'édition / tour de contrôle), donc un bouton
                // « Voir » resterait inerte. « Modifier » et le clic sur la ligne
                // ouvrent tous deux le dossier.
                EditAction::make()->label('Ouvrir le dossier'),
                ActionGroup::make([
                    ContractActions::envoyerDocumentsASigner(),
                    ContractActions::deposerDocumentsSignes(),
                    ContractActions::signer(),
                    ContractActions::envoyerSignature(),
                    ContractActions::simulerSignature(),
                    ContractActions::telechargerCerfa(),
                    // Lien vers le dossier OPCO ouvert automatiquement à la signature.
                    Action::make('voirOpco')
                        ->label('Voir le dossier OPCO')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('info')
                        ->visible(fn ($record): bool => $record->opcoFile !== null)
                        ->url(fn ($record): string => OpcoFileResource::getUrl(
                            'edit',
                            ['record' => $record->opcoFile],
                        )),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('Aucun contrat')
            ->emptyStateDescription('Créez un contrat d\'apprentissage dès qu\'un candidat est accepté : le dossier OPCO s\'ouvrira automatiquement à la transmission.');
    }
}
