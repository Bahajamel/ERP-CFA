<?php

namespace App\Filament\Editeur\Resources\Organisations;

use App\Filament\Editeur\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Editeur\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Editeur\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Editeur\Resources\Organisations\Schemas\OrganisationForm;
use App\Filament\Editeur\Resources\Organisations\Tables\OrganisationsTable;
use App\Models\Organisation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Gestion des CFA clients — réservée au panneau éditeur. Volontairement absente
 * du panneau CFA : un centre ne doit ni voir ni administrer les autres.
 */
class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'CFA clients';

    protected static ?string $modelLabel = 'CFA';

    protected static ?string $pluralModelLabel = 'CFA';

    public static function form(Schema $schema): Schema
    {
        return OrganisationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'create' => CreateOrganisation::route('/create'),
            'edit' => EditOrganisation::route('/{record}/edit'),
        ];
    }
}
