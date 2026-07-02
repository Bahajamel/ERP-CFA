<?php

namespace App\Scolarite;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\ServiceFait;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Génère la preuve de service fait (P1-15-2) : une attestation PDF récapitulant
 * l'assiduité mensuelle, archivée dans la GED (Document « preuve de service fait »).
 */
class ServiceFaitPreuve
{
    public function generer(ServiceFait $serviceFait, ?int $userId = null): Document
    {
        $serviceFait->loadMissing('promotion.formation', 'validatedBy');

        $pdf = Pdf::loadView('pdf.service-fait', ['sf' => $serviceFait]);
        $contenu = $pdf->output();

        $precedent = $serviceFait->documents()
            ->where('type', DocumentType::PreuveServiceFait->value)
            ->orderByDesc('version')
            ->first();

        $version = ($precedent?->version ?? 0) + 1;

        $document = Document::create([
            'documentable_type' => ServiceFait::class,
            'documentable_id' => $serviceFait->id,
            'type' => DocumentType::PreuveServiceFait,
            'statut' => DocumentStatut::Recu,
            'nom_fichier' => 'Service fait — '.$serviceFait->periodeLibelle(),
            'version' => $version,
            'previous_version_id' => $precedent?->id,
            'uploaded_by' => $userId,
        ]);

        $document->addMediaFromString($contenu)
            ->usingFileName('service-fait-'.$serviceFait->id.'-v'.$version.'.pdf')
            ->toMediaCollection('fichier');

        return $document;
    }
}
