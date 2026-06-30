<?php

namespace App\Filament\Resources\Matchings;

use App\Filament\Resources\Matchings\Pages\CreateMatching;
use App\Filament\Resources\Matchings\Pages\EditMatching;
use App\Filament\Resources\Matchings\Pages\ListMatchings;
use App\Filament\Resources\Matchings\Schemas\MatchingForm;
use App\Filament\Resources\Matchings\Tables\MatchingsTable;
use App\Models\Matching;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MatchingResource extends Resource
{
    protected static ?string $model = Matching::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_matching') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Matching';

    protected static ?string $modelLabel = 'proposition';

    protected static ?string $pluralModelLabel = 'propositions (matching)';

    public static function form(Schema $schema): Schema
    {
        return MatchingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatchingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatchings::route('/'),
            'create' => CreateMatching::route('/create'),
            'edit' => EditMatching::route('/{record}/edit'),
        ];
    }
}
