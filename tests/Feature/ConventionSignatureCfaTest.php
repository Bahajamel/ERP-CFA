<?php

use App\Cerfa\CerfaApprentissage;
use App\Documents\ConventionFormation;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Models\Contract;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Le CFA signe sa convention d'avance.
 *
 * La convention de formation lie le CFA et l'ENTREPRISE (l'apprenti n'en est pas
 * signataire) : signature et cachet du CFA vivent dans la Fiche du CFA, autant
 * les apposer à la génération — ne reste alors que la signature de l'entreprise.
 *
 * Le CERFA, lui, ne se pré-signe PAS : vérifié sur le formulaire officiel
 * 10103*14 embarqué (resources/cerfa/cerfa_10103-14.pdf, page 2), ses seuls
 * signataires sont l'employeur, l'apprenti(e) et le représentant légal de
 * l'apprenti mineur. Aucune zone de signature ni de cachet du CFA n'y figure ;
 * le cadre du bas est réservé à l'organisme en charge du dépôt (l'OPCO).
 */
function pngDeTest(): string
{
    // PNG 1×1 valide — dompdf doit pouvoir le décoder réellement.
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
}

/** PNG uni aux dimensions demandées — la largeur sert de signature dans le PDF. */
function pngUni(int $largeur, int $hauteur): string
{
    $image = imagecreatetruecolor($largeur, $hauteur);
    imagefill($image, 0, 0, imagecolorallocate($image, 220, 30, 30));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function contratPourConvention(): Contract
{
    return Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
}

function cfaAvec(string ...$collections): Organisation
{
    $cfa = Organisation::courante();

    $cfa->update([
        'ville' => 'Paris',
        'representant_nom' => 'Durand',
        'representant_prenom' => 'Claire',
        'representant_fonction' => 'Directrice',
    ]);

    foreach ($collections as $collection) {
        $cfa->addMediaFromString(pngDeTest())
            ->usingFileName($collection.'.png')
            ->toMediaCollection($collection);
    }

    return $cfa->fresh();
}

it('appose la signature et le cachet du CFA sur la convention', function () {
    cfaAvec('signature', 'cachet');

    $d = app(ConventionFormation::class)->donnees(contratPourConvention());

    expect($d['cfa_signature_image'])->toStartWith('data:image/png;base64,')
        ->and($d['cfa_cachet_image'])->toStartWith('data:image/png;base64,')
        // Le nom du signataire accompagne la signature : une image seule ne dit
        // pas qui signe.
        ->and($d['cfa_representant'])->toBe('Claire Durand (Directrice)');
});

it('embarque réellement les deux images dans le PDF', function () {
    $cfa = cfaAvec();

    // Deux images DISTINCTES : dompdf mutualise les identiques, on ne verrait
    // qu'un seul objet et le cachet manquant passerait inaperçu.
    $cfa->addMediaFromString(pngUni(60, 20))->usingFileName('signature.png')->toMediaCollection('signature');
    $cfa->addMediaFromString(pngUni(44, 44))->usingFileName('cachet.png')->toMediaCollection('cachet');

    $pdf = app(ConventionFormation::class)->pour(contratPourConvention());

    // Un PDF « assez gros » ne prouve rien : il le serait aussi si dompdf
    // ignorait les images. On compte les objets image réellement produits.
    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(preg_match_all('#/Subtype\s*/Image#', $pdf))->toBe(2)
        ->and(preg_match_all('#/Width\s+60#', $pdf))->toBe(1)
        ->and(preg_match_all('#/Width\s+44#', $pdf))->toBe(1);
});

it('n’embarque aucune image quand le CFA n’a ni signature ni cachet', function () {
    Organisation::courante()->update(['ville' => 'Paris']);

    $pdf = app(ConventionFormation::class)->pour(contratPourConvention());

    expect(preg_match_all('#/Subtype\s*/Image#', $pdf))->toBe(0);
});

it('n’annonce pas une signature qu’il n’a pas', function () {
    // Fiche du CFA sans signature ni cachet : la convention doit retomber sur la
    // mention « à signer », jamais afficher un cadre vide sous un document
    // présenté comme signé.
    Organisation::courante()->update(['ville' => 'Paris']);

    $d = app(ConventionFormation::class)->donnees(contratPourConvention());

    expect($d['cfa_signature_image'])->toBeNull()
        ->and($d['cfa_cachet_image'])->toBeNull();
});

it('se contente du cachet si la signature manque', function () {
    cfaAvec('cachet');

    $d = app(ConventionFormation::class)->donnees(contratPourConvention());

    expect($d['cfa_signature_image'])->toBeNull()
        ->and($d['cfa_cachet_image'])->toStartWith('data:image/png;base64,');
});

it('ignore un média dont le fichier a disparu du disque', function () {
    $cfa = cfaAvec('signature');
    $media = $cfa->getFirstMedia('signature');

    // La ligne existe, le fichier non — exactement la panne des 183 documents.
    @unlink($media->getPath());

    $d = app(ConventionFormation::class)->donnees(contratPourConvention());

    expect($d['cfa_signature_image'])->toBeNull();
});

it('ne pré-signe jamais le CERFA au nom du CFA', function () {
    cfaAvec('signature', 'cachet');

    $champs = app(CerfaApprentissage::class)->champs(contratPourConvention());

    // Vérifié sur le formulaire officiel embarqué : le CERFA 10103*14 n'a aucune
    // zone de signature ou de cachet du CFA. Y apposer une signature ajouterait
    // au document déposé à l'OPCO et à l'État quelque chose que le formulaire ne
    // prévoit pas. Ce test garde cette règle.
    $signature = array_filter(
        array_keys($champs),
        fn (string $cle): bool => str_contains($cle, 'signature') || str_contains($cle, 'cachet'),
    );

    expect($signature)->toBe([]);

    // Et la carte de positionnement officielle n'en définit aucune non plus.
    $zones = array_filter(
        array_keys(config('cerfa_map')),
        fn (string $cle): bool => str_contains($cle, 'signature') || str_contains($cle, 'cachet'),
    );

    expect($zones)->toBe([]);
});
