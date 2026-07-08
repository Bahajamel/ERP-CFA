<?php

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('media-library.disk_name', 'public'));
});

/** Faux PDF avec un vrai en-tête (accepté par la validation ET medialibrary). */
function fakePdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\nfaux PDF de test\n%%EOF");
}

function candidaturePayload(array $overrides = []): array
{
    return array_merge([
        'nom' => 'Martin',
        'prenom' => 'Léa',
        'email' => 'lea.martin@example.test',
        'telephone' => '0600000000',
        'date_naissance' => now()->subYears(22)->format('Y-m-d'),
        'adresse' => '1 rue de la Paix',
        'formation_visee_id' => Formation::factory()->create()->id,
        'piece_identite' => fakePdf('identite.pdf'),
        'cv' => fakePdf('cv.pdf'),
        'carte_vitale' => fakePdf('vitale.pdf'),
    ], $overrides);
}

it('affiche le formulaire public de candidature (sans authentification)', function () {
    $this->get(route('candidature.create'))
        ->assertOk()
        ->assertSee('Déposez votre candidature');
});

it('crée un candidat « Dossier incomplet » avec ses pièces (collections média)', function () {
    $this->post(route('candidature.store'), candidaturePayload())
        ->assertRedirect(route('candidature.merci'));

    $candidate = Candidate::where('email', 'lea.martin@example.test')->first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->statut)->toBe(CandidateStatut::Incomplet)
        ->and($candidate->source)->toBe('Candidature en ligne')
        ->and($candidate->getFirstMedia('cv'))->not->toBeNull()
        ->and($candidate->getFirstMedia('piece_identite'))->not->toBeNull()
        ->and($candidate->getFirstMedia('carte_vitale'))->not->toBeNull();
});

it('refuse une candidature sans CV', function () {
    $payload = candidaturePayload();
    unset($payload['cv']);

    $this->post(route('candidature.store'), $payload)->assertSessionHasErrors('cv');

    expect(Candidate::count())->toBe(0);
});

it('exige l\'attestation de création de projet à partir de 30 ans', function () {
    $payload = candidaturePayload(['date_naissance' => now()->subYears(35)->format('Y-m-d')]);

    $this->post(route('candidature.store'), $payload)->assertSessionHasErrors('attestation_projet');

    $payload['attestation_projet'] = fakePdf('projet.pdf');
    $this->post(route('candidature.store'), $payload)->assertRedirect(route('candidature.merci'));

    expect(Candidate::where('nom', 'Martin')->first()->getFirstMedia('attestation_projet'))->not->toBeNull();
});

it('n\'exige pas l\'attestation avant 30 ans', function () {
    $this->post(route('candidature.store'), candidaturePayload(['date_naissance' => now()->subYears(25)->format('Y-m-d')]))
        ->assertRedirect(route('candidature.merci'));
});

it('exige l\'email ET le téléphone', function () {
    $this->post(route('candidature.store'), candidaturePayload(['email' => '']))
        ->assertSessionHasErrors('email');

    $this->post(route('candidature.store'), candidaturePayload(['telephone' => '']))
        ->assertSessionHasErrors('telephone');

    expect(Candidate::count())->toBe(0);
});

it('refuse les caractères dangereux dans le nom et le téléphone (anti-XSS)', function () {
    $this->post(route('candidature.store'), candidaturePayload(['nom' => '<script>alert(1)</script>']))
        ->assertSessionHasErrors('nom');

    $this->post(route('candidature.store'), candidaturePayload(['telephone' => '06 12 <img src=x>']))
        ->assertSessionHasErrors('telephone');

    expect(Candidate::count())->toBe(0);
});

it('retire les balises HTML de l\'adresse avant enregistrement', function () {
    $this->post(route('candidature.store'), candidaturePayload([
        'adresse' => '12 rue de la Paix <b>75001</b> Paris',
    ]))->assertRedirect(route('candidature.merci'));

    expect(Candidate::first()->adresse)->toBe('12 rue de la Paix 75001 Paris');
});

it('accepte les noms accentués et composés', function () {
    $this->post(route('candidature.store'), candidaturePayload([
        'nom' => "N'Guessan-Dupré",
        'prenom' => 'Chloé Aïcha',
    ]))->assertRedirect(route('candidature.merci'));

    expect(Candidate::where('nom', "N'Guessan-Dupré")->exists())->toBeTrue();
});
