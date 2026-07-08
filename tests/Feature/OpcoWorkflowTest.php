<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\OpcoStatut;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

function dossierOpco(OpcoStatut $statut, ContractSignatureStatut $signature = ContractSignatureStatut::Signe): OpcoFile
{
    // Statuts déterministes : le statut contrat doit être cohérent avec la
    // signature (estSigne() regarde les deux champs).
    $contract = Contract::factory()->create([
        'statut_signature' => $signature,
        'statut_contrat' => $signature === ContractSignatureStatut::Signe
            ? \App\Enums\ContractStatut::Signe
            : \App\Enums\ContractStatut::EnvoyeSignature,
    ]);

    return OpcoFile::factory()->create([
        'contract_id' => $contract->id,
        'statut' => $statut,
        'motif_rejet' => null,
    ]);
}

it('interdit la création même du dossier OPCO si le contrat n\'est pas signé', function () {
    // Cycle apprenant : la garde intervient dès la création du dossier,
    // plus seulement à la transition « Prêt au dépôt ».
    expect(fn () => dossierOpco(OpcoStatut::APreparer, ContractSignatureStatut::NonSigne))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('autorise « Prêt au dépôt » si le contrat est signé', function () {
    $file = dossierOpco(OpcoStatut::APreparer, ContractSignatureStatut::Signe);

    $file->transitionTo(OpcoStatut::PretDepot);

    expect($file->fresh()->statut)->toBe(OpcoStatut::PretDepot);
});

it('exige un motif pour rejeter un dossier OPCO', function () {
    $file = dossierOpco(OpcoStatut::AttenteRetour);

    expect(fn () => $file->transitionTo(OpcoStatut::Rejete))
        ->toThrow(InvalidTransitionException::class);

    $file->update(['motif_rejet' => 'Pièce manquante au dossier']);
    $file->transitionTo(OpcoStatut::Rejete);

    expect($file->fresh()->statut)->toBe(OpcoStatut::Rejete);
});

it('crée automatiquement une action corrective au rejet', function () {
    $file = dossierOpco(OpcoStatut::AttenteRetour);
    $file->update(['motif_rejet' => 'Montant incohérent']);

    $file->transitionTo(OpcoStatut::Rejete);

    $file->refresh();
    expect($file->tasks()->count())->toBe(1)
        ->and($file->tasks()->first()->source)->toBe('auto')
        ->and($file->tasks()->first()->description)->toBe('Montant incohérent');
});

it('considère les statuts rejeté et en correction comme bloqués', function () {
    expect(OpcoStatut::bloques())->toContain(OpcoStatut::Rejete->value)
        ->and(OpcoStatut::bloques())->toContain(OpcoStatut::EnCorrection->value);
});

it('refuse une transition structurellement interdite', function () {
    $file = dossierOpco(OpcoStatut::APreparer);

    expect(fn () => $file->transitionTo(OpcoStatut::Accepte))
        ->toThrow(InvalidTransitionException::class);
});

it('réserve les dossiers OPCO aux rôles autorisés', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['Administratif']);
    $this->actingAs($admin);
    expect(OpcoFileResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create();
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(OpcoFileResource::canAccess())->toBeFalse();
});
