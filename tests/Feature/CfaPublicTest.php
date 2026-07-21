<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Organisation;
use App\Support\CfaPublic;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Les formulaires publics visent un CFA précis via le segment {cfa} de l'URL.
 * Sans lui, ils retombent sur le CFA par défaut — les liens historiques
 * (/candidature, /entreprise) continuent donc de fonctionner à l'identique.
 */

/** Second CFA, distinct de celui posé par TestCase. */
function autreCfa(): Organisation
{
    return Organisation::factory()->create(['slug' => 'cfa-lyon', 'actif' => true]);
}

/**
 * Dossier de candidature complet (3 pièces obligatoires). Helper local plutôt
 * que celui de CandidatureTest : les fonctions Pest sont chargées avec leur
 * fichier, en dépendre rendrait ce test tributaire de l'ordre d'exécution.
 */
function dossierCandidature(array $overrides = []): array
{
    $piece = fn (string $nom): UploadedFile => UploadedFile::fake()
        ->createWithContent($nom, "%PDF-1.4\nfaux PDF de test\n%%EOF");

    return array_merge([
        'nom' => 'Durand',
        'prenom' => 'Léa',
        'email' => 'lea.durand@example.test',
        'telephone' => '+33600000000',
        'date_naissance' => now()->subYears(22)->format('Y-m-d'),
        'formation_visee_id' => Formation::factory()->create()->id,
        'piece_identite' => $piece('identite.pdf'),
        'cv' => $piece('cv.pdf'),
        'carte_vitale' => $piece('vitale.pdf'),
    ], $overrides);
}

// ── Résolution du CFA ─────────────────────────────────────────────────────────

it('résout le CFA par son slug, et le CFA par défaut sans slug', function () {
    $lyon = autreCfa();

    expect(CfaPublic::resoudre('cfa-lyon')->id)->toBe($lyon->id)
        ->and(CfaPublic::resoudre(null)->id)->toBe(Organisation::defaut()->id);
});

it('renvoie 404 sur un slug inconnu plutôt que de rattacher au mauvais CFA', function () {
    $this->get('/candidature/cfa-inexistant')->assertNotFound();
    $this->get('/entreprise/cfa-inexistant')->assertNotFound();
});

it('renvoie 404 sur un CFA désactivé', function () {
    Organisation::factory()->create(['slug' => 'cfa-ferme', 'actif' => false]);

    $this->get('/candidature/cfa-ferme')->assertNotFound();
});

// ── Liens historiques (non-régression) ────────────────────────────────────────

it('sert toujours les anciens liens sans slug', function () {
    $this->get(route('candidature.create'))->assertOk();
    $this->get(route('entreprise.create'))->assertOk();

    expect(route('candidature.create'))->toEndWith('/candidature')
        ->and(route('entreprise.create'))->toEndWith('/entreprise');
});

it('ne confond pas les segments réservés avec un slug de CFA', function () {
    // /entreprise/lookup, /entreprise/besoin, /candidature/merci… sont des routes
    // à part entière : elles doivent gagner contre le paramètre {cfa?}.
    $this->getJson('/entreprise/lookup?siret=123')->assertStatus(422);
    $this->get('/candidature/merci')->assertOk();
    $this->get('/entreprise/merci')->assertOk();
    $this->get('/entreprise/besoin')->assertRedirect(route('entreprise.create'));
});

// ── Rattachement au bon CFA ───────────────────────────────────────────────────

it('rattache la candidature au CFA de l\'URL, pas au CFA par défaut', function () {
    $lyon = autreCfa();

    $this->get(route('candidature.create', ['cfa' => 'cfa-lyon']))
        ->assertOk()
        // Le CFA voyage dans un champ caché jusqu'à l'envoi.
        ->assertSee('name="cfa" value="cfa-lyon"', false);

    $this->post(route('candidature.store'), dossierCandidature([
        'cfa' => 'cfa-lyon',
        'nom' => 'Durand',
        'formation_visee_id' => Formation::factory()->create(['organisation_id' => $lyon->id])->id,
    ]))->assertRedirect(route('candidature.merci'));

    $candidat = Candidate::tousLesCfa()->where('nom', 'Durand')->firstOrFail();

    expect($candidat->organisation_id)->toBe($lyon->id)
        ->and($candidat->organisation_id)->not->toBe(Organisation::defaut()->id);
});

it('rattache l\'entreprise au CFA de l\'URL, pas au CFA par défaut', function () {
    $lyon = autreCfa();

    $this->get(route('entreprise.create', ['cfa' => 'cfa-lyon']))
        ->assertOk()
        ->assertSee('name="cfa" value="cfa-lyon"', false);

    $this->post(route('entreprise.store'), [
        'cfa' => 'cfa-lyon',
        'raison_sociale' => 'ACME Lyon',
        'siret' => '12345678900011',
        'contact_nom' => 'Martin',
        'contact_email' => 'rh@acme.test',
    ])->assertRedirect(route('entreprise.besoin'));

    $company = Company::tousLesCfa()->where('siret', '12345678900011')->firstOrFail();

    expect($company->organisation_id)->toBe($lyon->id)
        ->and($company->organisation_id)->not->toBe(Organisation::defaut()->id);
});

it('retombe sur le CFA par défaut quand aucun CFA n\'est transmis', function () {
    $this->post(route('candidature.store'), dossierCandidature(['nom' => 'Sansslug']))
        ->assertRedirect(route('candidature.merci'));

    expect(Candidate::tousLesCfa()->where('nom', 'Sansslug')->value('organisation_id'))
        ->toBe(Organisation::defaut()->id);
});

// ── Cloisonnement du catalogue ────────────────────────────────────────────────

it('ne propose que les formations du CFA visé', function () {
    $lyon = autreCfa();

    Formation::factory()->create([
        'libelle' => 'Patisserie Lyon',
        'organisation_id' => $lyon->id,
    ]);
    Formation::factory()->create([
        'libelle' => 'Boulangerie Paris',
        'organisation_id' => Organisation::defaut()->id,
    ]);

    $this->get(route('candidature.create', ['cfa' => 'cfa-lyon']))
        ->assertOk()
        ->assertSee('Patisserie Lyon')
        ->assertDontSee('Boulangerie Paris');
});

// ── Liens distribués depuis l'ERP ─────────────────────────────────────────────

it('génère le lien public du CFA courant', function () {
    $lyon = autreCfa();

    expect(CfaPublic::lien('candidature.create', $lyon))->toEndWith('/candidature/cfa-lyon')
        ->and(CfaPublic::lien('entreprise.create', $lyon))->toEndWith('/entreprise/cfa-lyon')
        // Hors contexte CFA : lien historique, qui vise le CFA par défaut.
        ->and(CfaPublic::lien('candidature.create', null))->toEndWith('/candidature');
});

it('propose le lien du tenant courant dans l\'ERP', function () {
    expect(CfaPublic::lien('entreprise.create', Filament::getTenant()))
        ->toEndWith('/entreprise/'.Filament::getTenant()->slug);
});
