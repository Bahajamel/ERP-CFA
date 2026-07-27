<?php

use App\Documents\ConventionFormation;
use App\Enums\DocumentType;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\Organisation;
use App\Models\User;
use App\Services\ContractDocumentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('media-library.disk_name', 'public'));
});

function cfaComplet(): void
{
    Organisation::courante()->update([
        'raison_sociale' => 'CFA V2S',
        'nom' => 'CFA V2S',
        'siret' => '11111111100011',
        'numero_uai' => '0011111X',
        'nda' => '11223344556',
        'representant_nom' => 'Durand',
        'representant_prenom' => 'Claire',
        'representant_fonction' => 'Directrice',
        'ville' => 'Paris',
        'adresse' => '5 avenue de la République',
        'code_postal' => '75011',
    ]);
}

function contratComplet(): Contract
{
    $formation = Formation::factory()->create(['libelle' => 'Concepteur Développeur', 'code_rncp' => 'RNCP37873']);
    $company = Company::factory()->create([
        'raison_sociale' => 'Webtech Solutions',
        'siret' => '81234567800012',
        'adresse' => '12 rue de la Paix',
        'code_postal' => '75001',
        'ville' => 'Paris',
    ]);
    CompanyContact::factory()->create([
        'company_id' => $company->id, 'is_principal' => true,
        'nom' => 'Rousseau', 'prenom' => 'Jean', 'email' => 'rh@webtech.fr', 'telephone' => '0102030405',
    ]);
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
        'lieu_formation' => '15 rue Garibaldi',
        'lieu_formation_code_postal' => '69003',
        'lieu_formation_ville' => 'Lyon',
        'duree_formation_heures' => 800,
        'cout_formation' => 8000,
    ]);
}

function service(): ContractDocumentService
{
    return app(ContractDocumentService::class);
}

it('génère une convention de formation PDF valide et non vide', function () {
    cfaComplet();

    $pdf = app(ConventionFormation::class)->pour(contratComplet());

    expect($pdf)->toBeString()
        ->and(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(5_000);
});

it('détecte les informations manquantes de la convention', function () {
    // Contrat minimal (pas de lieu de formation, CFA vide).
    $contract = Contract::factory()->create([
        'lieu_formation' => null,
        'lieu_formation_ville' => null,
    ]);

    $manquants = service()->champsManquantsConvention($contract);

    expect($manquants)->toContain('Lieu principal de formation')
        ->and($manquants)->toContain('SIRET du CFA');
});

it('ne signale aucun champ manquant pour un contrat complet', function () {
    cfaComplet();

    expect(service()->champsManquantsConvention(contratComplet()))->toBe([]);
});

it('enregistre la convention dans la GED du contrat (section Documents)', function () {
    cfaComplet();
    $contract = contratComplet();

    $document = service()->genererConvention($contract);

    expect($document->type)->toBe(DocumentType::Convention)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($contract->documents()->where('type', DocumentType::Convention->value)->exists())->toBeTrue();
});

it('génère le CERFA en GED sans casser la génération existante', function () {
    cfaComplet();
    $contract = contratComplet();

    $document = service()->genererCerfa($contract);

    expect($document->type)->toBe(DocumentType::Cerfa)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull();
});

it('calcule la complétude et l\'état documentaire du contrat', function () {
    cfaComplet();
    $contract = contratComplet();

    $avant = service()->completude($contract);
    expect($avant['cerfa']['etat'])->toBe(ContractDocumentService::ETAT_A_GENERER)
        ->and($avant['convention']['etat'])->toBe(ContractDocumentService::ETAT_A_GENERER);

    service()->genererCerfa($contract);
    service()->genererConvention($contract);

    $apres = service()->completude($contract->fresh());
    expect($apres['cerfa']['etat'])->toBe(ContractDocumentService::ETAT_GENERE)
        ->and($apres['convention']['etat'])->toBe(ContractDocumentService::ETAT_GENERE)
        ->and($apres['score'])->toBe(100);
});

it('marque le document « à régénérer » après modification du contrat', function () {
    cfaComplet();
    $contract = contratComplet();
    service()->genererConvention($contract);

    // Le contrat est modifié après la génération (date postérieure).
    Contract::query()->whereKey($contract->id)->update(['updated_at' => now()->addMinutes(10)]);

    $etat = service()->completude($contract->fresh());

    expect($etat['convention']['etat'])->toBe(ContractDocumentService::ETAT_A_REGENERER);
});

it('remplace le document existant lors d\'une régénération (pas de doublon)', function () {
    cfaComplet();
    $contract = contratComplet();

    service()->genererConvention($contract);
    service()->genererConvention($contract);

    expect($contract->documents()->where('type', DocumentType::Convention->value)->count())->toBe(1);
});

it('monte la page contrat (tour de contrôle) et génère la convention via l\'action', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administratif']);
    $this->actingAs($user);

    cfaComplet();
    $contract = contratComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->assertSuccessful()
        ->callAction('genererConvention');

    expect($contract->documents()->where('type', DocumentType::Convention->value)->exists())->toBeTrue();
});
