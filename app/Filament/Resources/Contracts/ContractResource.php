<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractSignatureStatut;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_contracts') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Contrats & OPCO';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Contrats';

    protected static ?string $modelLabel = 'contrat';

    protected static ?string $pluralModelLabel = 'contrats';

    /** Badge de navigation : contrats dont la signature n'est pas finalisée. */
    public static function getNavigationBadge(): ?string
    {
        $n = Contract::query()
            ->where('statut_signature', '!=', ContractSignatureStatut::Signe->value)
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Contrats à faire signer';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['candidate.nom', 'candidate.prenom', 'company.raison_sociale', 'code_rncp'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return trim(($record->candidate?->nom_complet ?? 'Contrat').' — '.($record->company?->raison_sociale ?? ''), ' —');
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return ['Statut' => $record->statut_contrat->getLabel()];
    }

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
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
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'edit' => EditContract::route('/{record}/edit'),
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
