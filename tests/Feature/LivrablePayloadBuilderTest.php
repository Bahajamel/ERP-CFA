<?php

use App\Livret\LivrablePayloadBuilder;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('construit un payload structuré complet depuis un contrat', function () {
    config([
        'cfa.nom' => 'CFA V2S',
        'cfa.representant_legal' => ['nom' => 'Martin', 'prenom' => 'Claire', 'fonction' => 'Directrice'],
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
        ->and($payload['dossier']['apprenant']['nom'])->toBe($candidate->nom)
        ->and($payload['dossier']['apprenant']['prenom'])->toBe($candidate->prenom)
        ->and($payload['dossier']['formation']['intitule'])->toBe($formation->libelle)
        ->and($payload['dossier']['employeur']['raison_sociale'])->toBe($company->raison_sociale)
        ->and($payload['dossier']['maitre_apprentissage']['nom'])->toBe($tuteur->nom)
        ->and($payload['livrables'])->not->toBeEmpty();
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
