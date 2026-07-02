<?php

use App\Jobs\GenererLivrablesJob;
use App\Livret\LivrablePackImporter;
use App\Livret\LivrablePayloadBuilder;
use App\Livret\LivretRsClient;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\CfaMissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function zipPourJob(array $fichiers): string
{
    $chemin = tempnam(sys_get_temp_dir(), 'jobzip');
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

function lancerJob(Contract $contract, ?int $userId): void
{
    (new GenererLivrablesJob($contract->id, $userId))->handle(
        app(LivrablePayloadBuilder::class),
        app(LivretRsClient::class),
        app(LivrablePackImporter::class),
    );
}

it('génère, importe les livrables et notifie l\'utilisateur', function () {
    Storage::fake('public');
    $this->seed(CfaMissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response(zipPourJob(['LivretAccueil_DUPONT_jean.pdf' => '%PDF']), 200)]);

    $user = User::factory()->create();
    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    lancerJob($contract, $user->id);

    expect(Document::where('livrable_code', 'livret_accueil')->where('source', 'livretrs')->count())->toBe(1)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});

it('notifie une erreur si le service échoue, sans créer de document', function () {
    Storage::fake('public');
    $this->seed(CfaMissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response('boom', 500)]);

    $user = User::factory()->create();
    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    lancerJob($contract, $user->id);

    expect(Document::where('source', 'livretrs')->count())->toBe(0)
        ->and($user->fresh()->notifications()->count())->toBe(1);
});
