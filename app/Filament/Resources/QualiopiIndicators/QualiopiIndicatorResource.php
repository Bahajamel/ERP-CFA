<?php

namespace App\Filament\Resources\QualiopiIndicators;

use App\Filament\Resources\QualiopiIndicators\Pages\EditQualiopiIndicator;
use App\Filament\Resources\QualiopiIndicators\Pages\ListQualiopiIndicators;
use App\Filament\Resources\QualiopiIndicators\RelationManagers\PreuvesRelationManager;
use App\Filament\Resources\QualiopiIndicators\Schemas\QualiopiIndicatorForm;
use App\Filament\Resources\QualiopiIndicators\Tables\QualiopiIndicatorsTable;
use App\Models\QualiopiIndicator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QualiopiIndicatorResource extends Resource
{
    protected static ?string $model = QualiopiIndicator::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_quality') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Qualité';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Registre Qualiopi';

    protected static ?string $modelLabel = 'indicateur Qualiopi';

    protected static ?string $pluralModelLabel = 'indicateurs Qualiopi';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return QualiopiIndicatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualiopiIndicatorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PreuvesRelationManager::class,
        ];
    }

    /** Badge de navigation : nombre d'indicateurs non conformes (rouge). */
    public static function getNavigationBadge(): ?string
    {
        $n = QualiopiIndicator::query()
            ->where('statut', \App\Enums\QualiopiStatut::NonConforme->value)
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualiopiIndicators::route('/'),
            'edit' => EditQualiopiIndicator::route('/{record}/edit'),
        ];
    }
}
