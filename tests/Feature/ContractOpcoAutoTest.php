<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ouvre automatiquement le dossier OPCO dès la signature du contrat', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::Signe, // satisfait la garde de signature
    ]);

    expect($contract->opcoFile()->exists())->toBeFalse();

    $contract->transitionTo(ContractStatut::Signe);

    $opco = $contract->fresh()->opcoFile;
    expect($opco)->not->toBeNull()
        ->and($opco->statut)->toBe(OpcoStatut::APreparer);
});

it('ne crée pas de doublon de dossier OPCO à la transmission', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    $contract->transitionTo(ContractStatut::Signe);       // crée le dossier OPCO
    $contract->transitionTo(ContractStatut::TransmisOpco); // ne doit pas en recréer

    expect($contract->opcoFile()->count())->toBe(1);
});
