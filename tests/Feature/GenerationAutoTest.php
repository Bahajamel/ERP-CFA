<?php

use App\Enums\DocumentSource;
use App\Livret\LivrablePackImporter;
use App\Livret\LivrablePayloadBuilder;
use App\Livret\LivretRsClient;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Document;
use Database\Seeders\CfaMissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Construit les octets d'un ZIP contenant [nom => contenu]. */
function zipDeGeneration(array $fichiers): string
{
    $chemin = tempnam(sys_get_temp_dir(), 'gen');
    $zip = new ZipArchive;
    $zip->open($chemin, ZipArchive::OVERWRITE);
    foreach ($fichiers as $nom => $contenu) {
        $zip->addFromString($nom, $contenu);
    }
    $zip->close();

    $octets = file_get_contents($chemin);
    @unlink($chemin);

    return $octets;
}

it('génère puis importe les livrables de bout en bout (côté ERP)', function () {
    Storage::fake('public');
    $this->seed(CfaMissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);

    Http::fake(['*' => Http::response(
        zipDeGeneration(['LivretAccueil_DUPONT_jean.pdf' => '%PDF faux']),
        200,
    )]);

    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    // Chaîne complète : payload → appel service → import GED.
    $payload = (new LivrablePayloadBuilder)->pour($contract);
    $zip = (new LivretRsClient)->genererLivrables($payload);
    $result = (new LivrablePackImporter)->import($contract, $zip, null);
    @unlink($zip);

    $document = Document::where('livrable_code', 'livret_accueil')->first();

    expect($result->importes)->toBe(1)
        ->and($document)->not->toBeNull()
        ->and($document->source)->toBe(DocumentSource::LivretRs)
        ->and($document->documentable_id)->toBe($candidate->id)
        ->and($document->missions()->pluck('numero')->sort()->values()->all())->toBe([1, 4]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/generate'));
});
