<?php

use App\Cerfa\CerfaApprentissage;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Models\CfaProfile;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function contratPourCerfa(): Contract
{
    $formation = Formation::factory()->create(['libelle' => 'Concepteur Développeur', 'code_rncp' => 'RNCP37873']);
    $company = Company::factory()->create([
        'raison_sociale' => 'Webtech Solutions',
        'siret' => '81234567800012',
        'adresse' => '12 rue de la Paix, 75001 Paris',
    ]);
    CompanyContact::factory()->create(['company_id' => $company->id, 'is_principal' => true, 'email' => 'rh@webtech.fr']);
    $tuteur = CompanyContact::factory()->create([
        'company_id' => $company->id, 'is_tuteur' => true,
        'nom' => 'Marty', 'prenom' => 'Georges', 'fonction' => 'Lead dev',
    ]);

    return Contract::factory()->create([
        'company_id' => $company->id,
        'formation_id' => $formation->id,
        'tuteur_id' => $tuteur->id,
        'code_rncp' => 'RNCP37873',
        'date_debut' => '2026-09-01',
        'date_fin' => '2028-08-31',
        'salaire_mensuel_brut' => 977.55,
        'statut_signature' => ContractSignatureStatut::Signe,
        'statut_contrat' => ContractStatut::Signe,
    ]);
}

it('génère un PDF CERFA valide et non vide', function () {
    CfaProfile::current()->update(['raison_sociale' => 'CFA V2S', 'ville' => 'Paris', 'siret' => '11111111100011']);

    $pdf = app(CerfaApprentissage::class)->pour(contratPourCerfa());

    expect($pdf)->toBeString()
        ->and(strlen($pdf))->toBeGreaterThan(50_000)
        // En-tête PDF valide.
        ->and(substr($pdf, 0, 5))->toBe('%PDF-');
});

it('conserve les deux pages du formulaire officiel', function () {
    $pdf = app(CerfaApprentissage::class)->pour(contratPourCerfa());

    // Le CERFA 10103*14 fait 2 pages : le modèle importé les conserve toutes.
    expect(substr_count($pdf, '/Type /Page'))->toBeGreaterThanOrEqual(2);
});

it('reste robuste si des données sont incomplètes (aucune exception)', function () {
    $contract = Contract::factory()->create([
        'tuteur_id' => null,
        'date_debut' => null,
        'date_fin' => null,
        'salaire_mensuel_brut' => null,
    ]);

    $pdf = app(CerfaApprentissage::class)->pour($contract);

    expect(substr($pdf, 0, 5))->toBe('%PDF-');
});
