<?php

namespace App\Filament\Resources\Corbeille;

use App\Filament\Resources\Corbeille\Pages\ListCorbeille;
use App\Models\Candidate;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Corbeille des candidats supprimés : archivés avec un motif, restaurables
 * pendant {@see Candidate::DELAI_PURGE_JOURS} jours, puis purgés
 * automatiquement (commande `candidats:purger-corbeille`).
 */
class CorbeilleResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Corbeille';

    protected static ?string $modelLabel = 'candidat supprimé';

    protected static ?string $pluralModelLabel = 'candidats supprimés';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_candidates');
    }

    /** Ne liste QUE les candidats supprimés (corbeille). */
    public static function getEloquentQuery(): Builder
    {
        return Candidate::onlyTrashed()->with('deletedBy');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Candidate::onlyTrashed()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn (Candidate $record) => $record->nom_complet)
                    ->searchable(['nom', 'prenom']),
                TextColumn::make('motif_suppression')
                    ->label('Motif de suppression')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('deletedBy.name')
                    ->label('Supprimé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('jours_avant_purge')
                    ->label('Purge dans')
                    ->badge()
                    ->state(fn (Candidate $record): string => $record->joursAvantPurge().' j')
                    ->color(fn (Candidate $record): string => match (true) {
                        $record->joursAvantPurge() <= 3 => 'danger',
                        $record->joursAvantPurge() <= 7 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('restaurer')
                    ->label('Restaurer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Candidate $record): string => "Restaurer {$record->nom_complet} ?")
                    ->modalDescription('Le candidat et tous ses dossiers réapparaîtront dans les listes.')
                    ->action(function (Candidate $record): void {
                        $record->restaurer();

                        Notification::make()->success()
                            ->title('Candidat restauré')
                            ->body('Il réapparaît dans la section Candidats.')
                            ->send();
                    }),
                Action::make('supprimerDefinitivement')
                    ->label('Supprimer définitivement')
                    ->icon('heroicon-o-fire')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Candidate $record): string => "Supprimer définitivement {$record->nom_complet} ?")
                    ->modalDescription('Action irréversible : le candidat et tous ses dossiers seront '
                        .'définitivement effacés de la base.')
                    ->modalSubmitActionLabel('Supprimer définitivement')
                    ->action(function (Candidate $record): void {
                        $record->forceDelete();

                        Notification::make()->success()->title('Candidat supprimé définitivement')->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('restaurerLot')
                        ->label('Restaurer')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Candidate $c) => $c->restaurer()))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('purgerLot')
                        ->label('Supprimer définitivement')
                        ->icon('heroicon-o-fire')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(fn (Candidate $c) => $c->forceDelete()))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('deleted_at', 'desc')
            ->emptyStateIcon('heroicon-o-trash')
            ->emptyStateHeading('Corbeille vide')
            ->emptyStateDescription('Les candidats supprimés apparaissent ici pendant '
                .Candidate::DELAI_PURGE_JOURS.' jours (restauration possible) avant purge automatique.');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorbeille::route('/'),
        ];
    }
}
