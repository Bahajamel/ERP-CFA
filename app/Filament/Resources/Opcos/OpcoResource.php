<?php

namespace App\Filament\Resources\Opcos;

use App\Filament\Resources\Opcos\Pages\CreateOpco;
use App\Filament\Resources\Opcos\Pages\EditOpco;
use App\Filament\Resources\Opcos\Pages\ListOpcos;
use App\Filament\Resources\Opcos\Schemas\OpcoForm;
use App\Filament\Resources\Opcos\Tables\OpcosTable;
use App\Models\Opco;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OpcoResource extends Resource
{
    protected static ?string $model = Opco::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_users') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'RÃ©fÃ©rentiels';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'OPCO';

    protected static ?string $modelLabel = 'OPCO';

    protected static ?string $pluralModelLabel = 'OPCO';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return OpcoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpcosTable::configure($table);
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
            'index' => ListOpcos::route('/'),
            'create' => CreateOpco::route('/create'),
            'edit' => EditOpco::route('/{record}/edit'),
        ];
    }
}
