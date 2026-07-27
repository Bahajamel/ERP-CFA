<?php

use App\Enums\DocumentSource;
use App\Livret\LivrablePackImporter;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Document;
use Database\Seeders\CfaMissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Construit une archive ZIP temporaire à partir de [nom => contenu]. */
function zipTemporaire(array $fichiers): string
{
    $chemin = tempnam(sys_get_temp_dir(), 'livret');

    $zip = new ZipArchive;
    $zip->open($chemin, ZipArchive::OVERWRITE);
    foreach ($fichiers as $nom => $contenu) {
        $zip->addFromString($nom, $contenu);
    }
    $zip->close();

    return $chemin;
}

function contratAvecApprenti(): Contract
{
    $candidate = Candidate::factory()->create();

    return Contract::factory()->create(['candidate_id' => $candidate->id]);
}

beforeEach(function () {
    Storage::fake('public');
});

it('importe chaque PDF comme document de l\'apprenti marqué « LivretRS »', function () {
    $this->seed(CfaMissionSeeder::class);
    $contrat = contratAvecApprenti();

    $zip = zipTemporaire([
        'LivretAccueil_DUPONT_jean.pdf' => '%PDF-1.4 faux',
        'ProcedureHandicap_DUPONT_jean.pdf' => '%PDF-1.4 faux',
        'lisez-moi.txt' => 'pas un livrable',
    ]);

    $result = (new LivrablePackImporter)->import($contrat, $zip);

    expect($result->importes)->toBe(2)
        ->and($result->reconnus)->toBe(2)
        ->and($result->ignores)->toContain('lisez-moi.txt')
        ->and(Document::count())->toBe(2);

    $document = Document::where('nom_fichier', 'LivretAccueil_DUPONT_jean.pdf')->first();
    expect($document->source)->toBe(DocumentSource::LivretRs)
        ->and($document->livrable_code)->toBe('livret_accueil')
        ->and($document->documentable_id)->toBe($contrat->candidate_id)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull();

    @unlink($zip);
});

it('rattache automatiquement les missions suggérées (verbatim du mapping)', function () {
    $this->seed(CfaMissionSeeder::class);
    $contrat = contratAvecApprenti();

    $zip = zipTemporaire([
        'LivretAccueil_DUPONT_jean.pdf' => '%PDF',   // missions 1 et 4
        'ProcedureHandicap_DUPONT_jean.pdf' => '%PDF', // mission 1
    ]);

    (new LivrablePackImporter)->import($contrat, $zip);

    $accueil = Document::where('livrable_code', 'livret_accueil')->first();
    $handicap = Document::where('livrable_code', 'procedure_handicap')->first();

    expect($accueil->missions()->pluck('numero')->sort()->values()->all())->toBe([1, 4])
        ->and($handicap->missions()->pluck('numero')->all())->toBe([1]);

    @unlink($zip);
});

it('importe une pièce non reconnue sans mission (à taguer à la main)', function () {
    $this->seed(CfaMissionSeeder::class);
    $contrat = contratAvecApprenti();

    $zip = zipTemporaire(['DocumentInconnu_X.pdf' => '%PDF']);

    $result = (new LivrablePackImporter)->import($contrat, $zip);

    $document = Document::first();
    expect($result->importes)->toBe(1)
        ->and($result->reconnus)->toBe(0)
        ->and($result->nonReconnus())->toBe(1)
        ->and($document->livrable_code)->toBeNull()
        ->and($document->missions()->count())->toBe(0);

    @unlink($zip);
});

it('refuse l\'import si le contrat n\'a pas d\'apprenti', function () {
    $this->seed(CfaMissionSeeder::class);
    $contrat = new Contract; // aucun candidat rattaché

    $zip = zipTemporaire(['LivretAccueil_X.pdf' => '%PDF']);

    expect(fn () => (new LivrablePackImporter)->import($contrat, $zip))
        ->toThrow(RuntimeException::class);

    @unlink($zip);
});
