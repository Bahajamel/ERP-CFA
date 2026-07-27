<?php

use App\Enums\ChecklistItemStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('dépose une preuve : crée le document dans la GED, le lie et passe la pièce à présente', function () {
    Storage::fake('public');

    $admission = Admission::factory()->create();
    $admission->genererChecklistObligatoire();
    $item = $admission->items()->first();

    expect($item->statut)->toBe(ChecklistItemStatut::Manquante);

    $file = UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf');
    $document = $item->attacherPreuve($file->getRealPath(), $file->getClientOriginalName());

    $item->refresh();

    expect($item->statut)->toBe(ChecklistItemStatut::Presente)
        ->and($item->document_id)->toBe($document->id)
        ->and($document->documentable_type)->toBe(Candidate::class)
        ->and($document->documentable_id)->toBe($admission->candidate_id)
        ->and($document->type)->toBe($item->document_type)
        ->and($document->nom_fichier)->toBe('cv.pdf')
        ->and($document->getFirstMedia('fichier'))->not->toBeNull();
});

it('réutilise le même document lors d\'un remplacement de fichier', function () {
    Storage::fake('public');

    $admission = Admission::factory()->create();
    $admission->genererChecklistObligatoire();
    $item = $admission->items()->first();

    $v1 = UploadedFile::fake()->create('v1.pdf', 100);
    $doc1 = $item->attacherPreuve($v1->getRealPath(), 'v1.pdf');

    $v2 = UploadedFile::fake()->create('v2.pdf', 100);
    $doc2 = $item->attacherPreuve($v2->getRealPath(), 'v2.pdf');

    expect($doc2->id)->toBe($doc1->id)
        ->and(Document::count())->toBe(1)
        ->and($item->fresh()->document->nom_fichier)->toBe('v2.pdf');
});
