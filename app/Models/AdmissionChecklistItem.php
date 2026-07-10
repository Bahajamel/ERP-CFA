<?php

namespace App\Models;

use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionChecklistItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'est_obligatoire' => 'boolean',
            'statut' => ChecklistItemStatut::class,
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Dépose le fichier justificatif de cette pièce : crée (ou réutilise) le
     * document dans la GED rattaché au candidat, y attache le fichier, lie la
     * pièce au document et la passe à « présente ». Un seul geste métier.
     */
    public function attacherPreuve(string $cheminFichier, string $nomFichier, ?int $userId = null): Document
    {
        $candidate = $this->admission?->candidate;

        if (! $candidate instanceof Candidate) {
            throw new RuntimeException('Impossible de rattacher une preuve : candidat introuvable.');
        }

        $document = $this->document()->first() ?? Document::create([
            'documentable_type' => Candidate::class,
            'documentable_id' => $candidate->id,
            'type' => $this->document_type,
            'statut' => DocumentStatut::Recu,
            'uploaded_by' => $userId,
        ]);

        $document->addMedia($cheminFichier)
            ->usingFileName($nomFichier)
            ->toMediaCollection('fichier');

        $document->forceFill(['nom_fichier' => $nomFichier])->save();

        $this->update([
            'document_id' => $document->id,
            'statut' => ChecklistItemStatut::Presente,
        ]);

        return $document;
    }
}
