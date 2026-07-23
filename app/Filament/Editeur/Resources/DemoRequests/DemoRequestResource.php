<?php

namespace App\Filament\Editeur\Resources\DemoRequests;

use App\Filament\Editeur\Resources\DemoRequests\Pages\EditDemoRequest;
use App\Filament\Editeur\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Filament\Editeur\Resources\DemoRequests\Schemas\DemoRequestForm;
use App\Filament\Editeur\Resources\DemoRequests\Tables\DemoRequestsTable;
use App\Models\DemoRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Demandes de démonstration reçues depuis le site vitrine public.
 *
 * Réservé au panneau éditeur : ce sont des prospects de la plateforme, pas des
 * données d'un CFA client. Aucune création manuelle — elles arrivent par le
 * formulaire public ; ici on les qualifie et on suit leur avancement.
 */
class DemoRequestResource extends Resource
{
    protected static ?string $model = DemoRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Demandes de démo';

    protected static ?string $modelLabel = 'demande de démonstration';

    protected static ?string $pluralModelLabel = 'demandes de démonstration';

    /** Les demandes sont déposées par le public, jamais saisies ici. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Pastille de navigation : nombre de demandes encore à traiter. */
    public static function getNavigationBadge(): ?string
    {
        $ouvertes = DemoRequest::query()->aTraiter()->count();

        return $ouvertes > 0 ? (string) $ouvertes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return DemoRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DemoRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDemoRequests::route('/'),
            'edit' => EditDemoRequest::route('/{record}/edit'),
        ];
    }
}
