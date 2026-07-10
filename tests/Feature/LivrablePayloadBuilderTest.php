<?php

use App\Livret\LivrablePayloadBuilder;
use App\Models\Candidate;
use App\Models\CfaProfile;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('construit un payload structuré complet depuis un contrat et le profil CFA', function () {
    CfaProfile::current()->update([
        'nom' => 'CFA V2S',
        'representant_nom' => 'Martin',
        'representant_prenom' => 'Claire',
        'representant_fonction' => 'Directrice',
        'theme_defaut' => 'premium',
        'format_defaut' => 'pdf_docx',
    ]);

    $company = Company::factory()->create();
    $tuteur = CompanyContact::factory()->tuteur()->create(['company_id' => $company->id]);
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create([
        'candidate_id' => $candidate->id,
        'company_id' => $company->id,
        'formation_id' => $formation->id,
        'tuteur_id' => $tuteur->id,
    ]);

    $payload = (new LivrablePayloadBuilder)->pour($contract);

    expect($payload['cfa']['nom'])->toBe('CFA V2S')
        ->and($payload['cfa']['representant_legal']['nom'])->toBe('Martin')
        ->and($payload['cfa']['representant_legal']['fonction'])->toBe('Directrice')
        ->and($payload['dossier']['apprenant']['nom'])->toBe($candidate->nom)
        ->and($payload['dossier']['formation']['intitule'])->toBe($formation->libelle)
        ->and($payload['dossier']['employeur']['raison_sociale'])->toBe($company->raison_sociale)
        ->and($payload['dossier']['maitre_apprentissage']['nom'])->toBe($tuteur->nom)
        ->and($payload['theme_code'])->toBe('premium')
        ->and($payload['format'])->toBe('pdf_docx')
        ->and($payload['livrables'])->not->toBeEmpty();
});

it('inclut le logo du profil (base64) dans les assets du payload', function () {
    Storage::fake('public');
    $profile = CfaProfile::current();
    $profile->addMediaFromString('contenu-logo-png')->usingFileName('logo.png')->toMediaCollection('logo');

    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    $payload = (new LivrablePayloadBuilder)->pour($contract);

    expect($payload['assets'])->toHaveKey('logo')
        ->and(base64_decode($payload['assets']['logo']))->toBe('contenu-logo-png')
        ->and($payload['assets'])->not->toHaveKey('signature');
});

it('omet la clé assets quand aucune pièce n\'est configurée (évite [] au lieu de {})', function () {
    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    $payload = (new LivrablePayloadBuilder)->pour($contract);

    expect($payload)->not->toHaveKey('assets');
});

it('n\'inclut ni NIR ni CERFA dans le payload (minimisation RGPD)', function () {
    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create(['candidate_id' => $candidate->id]);

    $payload = (new LivrablePayloadBuilder)->pour($contract);

    expect($payload['dossier']['apprenant'])->not->toHaveKey('nir')
        ->and($payload['dossier'])->not->toHaveKey('cerfa_source_path');
});

it('refuse un contrat sans apprenti', function () {
    expect(fn () => (new LivrablePayloadBuilder)->pour(new Contract))
        ->toThrow(RuntimeException::class);
});
