<?php

use App\Livret\LivrablesArchive;
use App\Models\Candidate;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function livrableDe(Candidate $candidate, string $nom): Document
{
    $doc = Document::factory()->create([
        'documentable_id' => $candidate->id,
        'documentable_type' => $candidate->getMorphClass(),
        'source' => 'livretrs',
    ]);
    $doc->addMediaFromString('%PDF faux')->usingFileName($nom)->toMediaCollection('fichier');

    return $doc;
}

it('construit un ZIP de tous les livrables LivretRS de l\'apprenti', function () {
    Storage::fake('public');
    $candidate = Candidate::factory()->create();
    livrableDe($candidate, 'LivretAccueil.pdf');
    livrableDe($candidate, 'ReglementInterieur.pdf');

    $zip = app(LivrablesArchive::class)->pour($candidate->fresh());

    expect($zip)->not->toBeNull()->and(is_file($zip))->toBeTrue();

    $za = new ZipArchive;
    $za->open($zip);
    expect($za->numFiles)->toBe(2);
    $za->close();

    @unlink($zip);
});

it('renvoie null quand l\'apprenti n\'a aucun livrable généré', function () {
    Storage::fake('public');
    $candidate = Candidate::factory()->create();

    expect(app(LivrablesArchive::class)->pour($candidate))->toBeNull();
});
