<?php

namespace App\Filament\Resources\Ruptures;

use App\Filament\Resources\Ruptures\Pages\CreateRupture;
use App\Filament\Resources\Ruptures\Pages\EditRupture;
use App\Filament\Resources\Ruptures\Pages\ListRuptures;
use App\Filament\Resources\Ruptures\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Ruptures\Schemas\RuptureForm;
use App\Filament\Resources\Ruptures\Tables\RupturesTable;
use App\Models\Rupture;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RuptureResource extends Resource
{
    protected static ?string $model = Rupture::class;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_ruptures');
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-x-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Contrats & OPCO';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Ruptures';

    protected static ?string $modelLabel = 'rupture';

    protected static ?string $pluralModelLabel = 'ruptures';

    public static function form(Schema $schema): Schema
    {
        return RuptureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RupturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRuptures::route('/'),
            'create' => CreateRupture::route('/create'),
            'edit' => EditRupture::route('/{record}/edit'),
        ];
    }
}
