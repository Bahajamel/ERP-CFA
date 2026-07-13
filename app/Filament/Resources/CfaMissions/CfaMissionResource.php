<?php

namespace App\Filament\Resources\CfaMissions;

use App\Filament\Resources\CfaMissions\Pages\ListCfaMissions;
use App\Filament\Resources\CfaMissions\Tables\CfaMissionsTable;
use App\Models\CfaMission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Registre des 14 missions du CFA (article L6231-2). Référentiel figé : pas de
 * création ni de modification depuis l'UI, seulement la lecture du texte
 * officiel et le suivi de la couverture documentaire (livrables rattachés).
 */
class CfaMissionResource extends Resource
{
    protected static ?string $model = CfaMission::class;

    // Référence nationale partagée (14 missions L6231-2) : non cloisonné par CFA.
    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_documents') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Missions CFA (L6231-2)';

    protected static ?string $modelLabel = 'mission CFA';

    protected static ?string $pluralModelLabel = 'missions CFA';

    protected static ?string $recordTitleAttribute = 'titre';

    public static function table(Table $table): Table
    {
        return CfaMissionsTable::configure($table);
    }

    /** Badge de navigation : nombre de missions sans aucun livrable rattaché. */
    public static function getNavigationBadge(): ?string
    {
        $n = CfaMission::query()->doesntHave('documents')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $n = CfaMission::query()->doesntHave('documents')->count();

        return $n > 0
            ? $n.' mission(s) sur 14 sans aucun livrable rattaché'
            : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCfaMissions::route('/'),
        ];
    }
}
