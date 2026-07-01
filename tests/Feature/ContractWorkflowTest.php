<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\OpcoStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\Document;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

function contratEnvoyeSignature(): Contract
{
    return Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::NonSigne,
    ]);
}

it('interdit le passage à « Signé » sans document contractuel ni signature', function () {
    $contract = contratEnvoyeSignature();

    expect($contract->canTransitionTo(ContractStatut::Signe))->toBeFalse();
    expect(fn () => $contract->transitionTo(ContractStatut::Signe))
        ->toThrow(InvalidTransitionException::class);
    expect($contract->fresh()->statut_contrat)->toBe(ContractStatut::EnvoyeSignature);
});

it('autorise « Signé » quand la signature est marquée signée', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    $contract->transitionTo(ContractStatut::Signe);

    expect($contract->fresh()->statut_contrat)->toBe(ContractStatut::Signe);
});

it('autorise « Signé » quand un document contractuel est associé et fixe la signature', function () {
    $contract = contratEnvoyeSignature();
    Document::factory()->for($contract, 'documentable')->create(['type' => DocumentType::Contrat]);

    expect($contract->canTransitionTo(ContractStatut::Signe))->toBeTrue();

    $contract->transitionTo(ContractStatut::Signe);

    $contract->refresh();
    expect($contract->statut_contrat)->toBe(ContractStatut::Signe)
        ->and($contract->statut_signature)->toBe(ContractSignatureStatut::Signe);
});

it('ouvre automatiquement le dossier OPCO à la transmission', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Signe,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    expect($contract->opcoFile)->toBeNull();

    $contract->transitionTo(ContractStatut::TransmisOpco);

    $contract->refresh();
    expect($contract->opcoFile)->not->toBeNull()
        ->and($contract->opcoFile->statut)->toBe(OpcoStatut::APreparer);
});

it('refuse une transition structurellement interdite', function () {
    $contract = Contract::factory()->create(['statut_contrat' => ContractStatut::Brouillon]);

    expect(fn () => $contract->transitionTo(ContractStatut::Signe))
        ->toThrow(InvalidTransitionException::class);
});

it('réserve les contrats aux rôles autorisés', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['Administratif']);
    $this->actingAs($admin);
    expect(ContractResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create();
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(ContractResource::canAccess())->toBeFalse();
});
