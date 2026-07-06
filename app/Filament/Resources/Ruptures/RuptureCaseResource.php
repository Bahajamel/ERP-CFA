<?php

namespace App\Filament\Resources\Ruptures;

use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\Pages\CreateRuptureCase;
use App\Filament\Resources\Ruptures\Pages\EditRuptureCase;
use App\Filament\Resources\Ruptures\Pages\ListRuptureCases;
use App\Filament\Resources\Ruptures\Schemas\RuptureCaseForm;
use App\Filament\Resources\Ruptures\Tables\RuptureCasesTable;
use App\Models\RuptureCase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuptureCaseResource extends Resource
{
    protected static ?string $model = RuptureCase::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_ruptures') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Contrats & OPCO';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Dossiers de rupture';

    protected static ?string $modelLabel = 'dossier de rupture';

    protected static ?string $pluralModelLabel = 'dossiers de rupture';

    /** Badge de navigation : nombre de dossiers encore ouverts (rouge). */
    public static function getNavigationBadge(): ?string
    {
        $ouverts = static::getModel()::query()
            ->whereIn('statut', RuptureStatut::ouverts())
            ->count();

        return $ouverts > 0 ? (string) $ouverts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['contract.candidate.nom', 'contract.candidate.prenom', 'contract.company.raison_sociale'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return 'Rupture — '.($record->contract?->candidate?->nom_complet ?? 'apprenti');
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return ['Statut' => $record->statut->getLabel(), 'Motif' => $record->motif->getLabel()];
    }

    public static function form(Schema $schema): Schema
    {
        return RuptureCaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RuptureCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRuptureCases::route('/'),
            'create' => CreateRuptureCase::route('/create'),
            'edit' => EditRuptureCase::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
