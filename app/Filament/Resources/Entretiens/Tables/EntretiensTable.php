<?php

namespace App\Filament\Resources\Entretiens\Tables;

use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Filament\Resources\Entretiens\EntretienActions;
use App\Models\Entretien;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntretiensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Masque les entretiens dont le candidat a été supprimé (corbeille).
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('candidate'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn (Entretien $record) => $record->candidate?->nom_complet)
                    ->description(fn (Entretien $record) => $record->candidate?->statut->getLabel())
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('date_entretien')
                    ->label('Créneau')
                    ->getStateUsing(fn (Entretien $record): string => $record->creneauLisible())
                    ->sortable(),
                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('resultat')
                    ->label('Décision')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'accepte' ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state): string => $state === 'accepte' ? 'Accepté' : 'Refusé')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(EntretienStatut::class),
                SelectFilter::make('mode')
                    ->label('Mode')
                    ->options(EntretienMode::class),
                Filter::make('a_venir')
                    ->label('À venir uniquement')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('date_entretien', '>=', now()->toDateString())
                        ->whereIn('statut', [EntretienStatut::Planifie->value, EntretienStatut::APlanifier->value])),
            ])
            // Listes déroulantes toujours visibles en barre au-dessus du tableau
            // (au lieu du menu déroulant « Filtres »), comme Candidats et Entreprises.
            // Filtres instantanés (sans bouton « Appliquer ») pour une barre compacte.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->recordActions([
                EntretienActions::planifier(),
                EntretienActions::reprogrammer(),
                EntretienActions::marquerRealise(),
                EntretienActions::accepterCandidat(),
                EntretienActions::refuserCandidat(),
                ActionGroup::make([
                    EntretienActions::marquerAbsent(),
                    EntretienActions::annuler(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('Aucun entretien pour le moment')
            ->emptyStateDescription('Planifiez un entretien depuis la fiche candidat ou avec le bouton '
                .'« Planifier un entretien » — le candidat passera automatiquement à « Entretien prévu ».');
    }
}
