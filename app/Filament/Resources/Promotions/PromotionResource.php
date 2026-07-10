<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\RelationManagers\ApprentisRelationManager;
use App\Filament\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Resources\Promotions\Tables\PromotionsTable;
use App\Models\Promotion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_formations') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Classes / Promotions';

    protected static ?string $modelLabel = 'classe';

    protected static ?string $pluralModelLabel = 'classes';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ApprentisRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }
}
