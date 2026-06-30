<?php

namespace App\Filament\Resources\OpcoFiles;

use App\Filament\Resources\OpcoFiles\Pages\CreateOpcoFile;
use App\Filament\Resources\OpcoFiles\Pages\EditOpcoFile;
use App\Filament\Resources\OpcoFiles\Pages\ListOpcoFiles;
use App\Filament\Resources\OpcoFiles\Schemas\OpcoFileForm;
use App\Filament\Resources\OpcoFiles\Tables\OpcoFilesTable;
use App\Models\OpcoFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OpcoFileResource extends Resource
{
    protected static ?string $model = OpcoFile::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_opco') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Admission & Contrats';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Dossiers OPCO';

    protected static ?string $modelLabel = 'dossier OPCO';

    protected static ?string $pluralModelLabel = 'dossiers OPCO';

    public static function form(Schema $schema): Schema
    {
        return OpcoFileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpcoFilesTable::configure($table);
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
            'index' => ListOpcoFiles::route('/'),
            'create' => CreateOpcoFile::route('/create'),
            'edit' => EditOpcoFile::route('/{record}/edit'),
        ];
    }
}
