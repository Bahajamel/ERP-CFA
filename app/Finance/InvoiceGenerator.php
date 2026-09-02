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
    /**
     * Contenu brut du PDF proforma d'une facture (pour téléchargement direct,
     * sans archivage). Le proforma n'a aucune valeur comptable.
     */
    public function pdf(Invoice $invoice): string
    {
        $invoice->loadMissing('financeLine.contract.candidate', 'financeLine.contract.company');

        return Pdf::loadView('pdf.facture', ['invoice' => $invoice])->output();
    }

    public function generer(Invoice $invoice, ?int $userId = null): Document
    {
        $contenu = $this->pdf($invoice);

        $document = $this->creerDocument(
            $invoice,
            $invoice->numero ?: 'Facture brouillon #'.$invoice->id,
            $userId,
        );

        $document->addMediaFromString($contenu)
            ->usingFileName('facture-'.$invoice->id.'-v'.$document->version.'.pdf')
            ->toMediaCollection('fichier');

        return $document;
    }

    /**
     * Importe la facture comptable officielle (PDF externe) comme Document
     * « Facture » versionné, rattaché à la facture. La pièce fiscale fait foi :
     * elle vient de la comptabilité, l'ERP ne fait que l'archiver.
     */
    public function importer(Invoice $invoice, string $chemin, ?string $nomOriginal, ?int $userId = null): Document
    {
        $document = $this->creerDocument(
            $invoice,
            $nomOriginal ?: ($invoice->numero ?: 'Facture importée #'.$invoice->id),
            $userId,
        );

        $document->addMediaFromDisk($chemin, 'public')
            ->toMediaCollection('fichier');

        return $document;
    }

    /** Crée le Document GED versionné (chaîné au précédent) rattaché à la facture. */
    private function creerDocument(Invoice $invoice, string $libelle, ?int $userId): Document
    {
        $precedent = $invoice->documents()
            ->where('type', DocumentType::Facture->value)
            ->orderByDesc('version')
            ->first();

        return Document::create([
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoice->id,
            'type' => DocumentType::Facture,
            'statut' => DocumentStatut::Recu,
            'nom_fichier' => $libelle,
            'version' => ($precedent?->version ?? 0) + 1,
            'previous_version_id' => $precedent?->id,
            'uploaded_by' => $userId,
        ]);
    }
}