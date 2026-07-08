<?php

use App\Enums\CompanyStatut;
use App\Models\Company;
use App\Models\Opco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** Mocke l'Annuaire des Entreprises (identité) + l'API SIRO France Compétences (OPCO). */
function fakeEntrepriseApis(string $opcoNom = 'OPCO Atlas'): void
{
    Http::fake([
        'recherche-entreprises.api.gouv.fr/*' => Http::response(['results' => [[
            'nom_raison_sociale' => 'ACME SA',
            'section_activite_principale' => 'J',
            'activite_principale' => '62.01Z',
            'siege' => [
                'siret' => '12345678900011',
                'numero_voie' => '1', 'type_voie' => 'RUE', 'libelle_voie' => 'DE LA PAIX',
                'code_postal' => '75001', 'libelle_commune' => 'PARIS',
            ],
        ]]]),
        'api.francecompetences.fr/*' => Http::response(['opcoGestion' => ['nom' => $opcoNom], 'opcoDsn' => ['nom' => $opcoNom]]),
    ]);
}

it('affiche le formulaire public entreprise', function () {
    $this->get(route('entreprise.create'))
        ->assertOk()
        ->assertSee('Devenez entreprise partenaire');
});

it('auto-remplit depuis le SIRET (identité Annuaire + OPCO France Compétences)', function () {
    fakeEntrepriseApis('OPCO Atlas');

    $reponse = $this->getJson(route('entreprise.lookup', ['siret' => '12345678900011']))
        ->assertOk()
        ->assertJson([
            'trouve' => true,
            'raison_sociale' => 'ACME SA',
            'secteur' => 'Information et communication',
            'opco_nom' => 'OPCO Atlas',
        ]);

    // L'OPCO a été rattaché au référentiel local.
    expect($reponse->json('opco_id'))->toBe(Opco::where('nom', 'OPCO Atlas')->value('id'));
});

it('rejette un SIRET invalide au lookup', function () {
    $this->getJson(route('entreprise.lookup', ['siret' => '123']))->assertStatus(422);
});

it('crée une entreprise « Prospect » avec son contact principal', function () {
    $this->post(route('entreprise.store'), [
        'raison_sociale' => 'ACME SA',
        'siret' => '12345678900011',
        'secteur' => 'Information et communication',
        'adresse' => '1 rue de la Paix',
        'contact_nom' => 'Dupont',
        'contact_email' => 'contact@acme.test',
        'contact_fonction' => 'RH',
    ])->assertRedirect(route('entreprise.merci'));

    $company = Company::where('siret', '12345678900011')->first();

    expect($company)->not->toBeNull()
        ->and($company->statut)->toBe(CompanyStatut::Prospect)
        ->and($company->contacts()->where('is_principal', true)->where('nom', 'Dupont')->exists())->toBeTrue();
});

it('refuse les caractères dangereux dans le contact (anti-XSS) et nettoie la raison sociale', function () {
    $payload = [
        'raison_sociale' => 'ACME <script>alert(1)</script> SA',
        'siret' => '12345678900011',
        'contact_nom' => '<img src=x onerror=alert(1)>',
        'contact_email' => 'contact@acme.test',
    ];

    // Le nom du contact, une fois les balises retirées, est invalide → rejeté.
    $this->post(route('entreprise.store'), $payload)->assertSessionHasErrors('contact_nom');

    // Avec un contact valide, la raison sociale est enregistrée sans balises.
    $payload['contact_nom'] = 'Dupont';
    $this->post(route('entreprise.store'), $payload)->assertRedirect(route('entreprise.merci'));

    expect(Company::where('siret', '12345678900011')->value('raison_sociale'))
        ->toBe('ACME alert(1) SA');
});

it('refuse un SIRET invalide et un doublon à la création', function () {
    $this->post(route('entreprise.store'), ['raison_sociale' => 'X', 'siret' => '123', 'contact_nom' => 'D', 'contact_email' => 'd@x.fr'])
        ->assertSessionHasErrors('siret');

    Company::factory()->create(['siret' => '12345678900011']);
    $this->post(route('entreprise.store'), ['raison_sociale' => 'X', 'siret' => '12345678900011', 'contact_nom' => 'D', 'contact_email' => 'd@x.fr'])
        ->assertSessionHasErrors('siret');
});
