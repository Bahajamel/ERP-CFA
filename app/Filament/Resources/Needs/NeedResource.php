<?php

namespace App\Filament\Resources\Needs;

use App\Filament\Resources\Needs\Pages\CreateNeed;
use App\Filament\Resources\Needs\Pages\EditNeed;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Filament\Resources\Needs\RelationManagers\MatchingsRelationManager;
use App\Filament\Resources\Needs\Schemas\NeedForm;
use App\Filament\Resources\Needs\Tables\NeedsTable;
use App\Models\Need;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NeedResource extends Resource
{
    protected static ?string $model = Need::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_needs') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Besoins entreprises';

    protected static ?string $modelLabel = 'besoin';

    protected static ?string $pluralModelLabel = 'besoins entreprises';

    protected static ?string $recordTitleAttribute = 'intitule_poste';

    public static function form(Schema $schema): Schema
    {
        return NeedForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NeedsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MatchingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNeeds::route('/'),
            'create' => CreateNeed::route('/create'),
            'edit' => EditNeed::route('/{record}/edit'),
        ];
    }
}
