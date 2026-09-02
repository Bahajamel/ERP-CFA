<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getSubheading(): ?string
    {
        return 'Les factures sont générées automatiquement depuis l\'échéancier des dossiers OPCO acceptés. '
            .'Émettez-les (numéro comptable), téléchargez le proforma, importez la facture officielle, ou annulez.';
    }
}