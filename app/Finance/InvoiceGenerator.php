<?php

namespace App\Finance;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Génère le PDF (brouillon ou définitif) d'une facture (P1-16-3) et l'archive
 * dans la GED en tant que Document « Facture » versionné, rattaché à la facture.
 */
class InvoiceGenerator
{
    public function generer(Invoice $invoice, ?int $userId = null): Document
    {
        $invoice->loadMissing('financeLine.contract.candidate', 'financeLine.contract.company');

        $pdf = Pdf::loadView('pdf.facture', ['invoice' => $invoice]);
        $contenu = $pdf->output();

        $precedent = $invoice->documents()
            ->where('type', DocumentType::Facture->value)
            ->orderByDesc('version')
            ->first();

        $version = ($precedent?->version ?? 0) + 1;
        $libelle = $invoice->numero ?: 'Facture brouillon #'.$invoice->id;

        $document = Document::create([
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
            'type' => DocumentType::Facture,
            'statut' => DocumentStatut::Recu,
            'nom_fichier' => $libelle,
            'version' => $version,
            'previous_version_id' => $precedent?->id,
            'uploaded_by' => $userId,
        ]);

        $document->addMediaFromString($contenu)
            ->usingFileName('facture-'.$invoice->id.'-v'.$version.'.pdf')
            ->toMediaCollection('fichier');

        return $document;
    }
}
