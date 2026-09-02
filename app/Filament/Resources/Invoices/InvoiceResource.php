<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatut;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Écran de gestion des factures (Phase A) : liste filtrable + actions
 * opérationnelles (émettre, proforma, importer, annuler). Complète le dashboard
 * Finance (lecture seule). Les factures naissent automatiquement de l'échéancier
 * des dossiers OPCO acceptés — on ne les crée pas à la main ici.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_finance') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Facturation';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Factures';

    protected static ?string $modelLabel = 'facture';

    protected static ?string $pluralModelLabel = 'factures';

    /** Badge de navigation : factures émises et échues (à relancer). */
    public static function getNavigationBadge(): ?string
    {
        $n = Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereDate('date_echeance', '<', now())
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Factures échues — relance recommandée';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'destinataire', 'financeLine.contract.candidate.nom'];
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}