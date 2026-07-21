<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\SignatureRequestStatut;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\SignatureService;
use App\Signature\Providers\NullSignatureProvider;
use App\Signature\Providers\SimulationSignatureProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function serviceSimu(): SignatureService
{
    return new SignatureService(new SimulationSignatureProvider);
}

/** Contrat prêt à être envoyé en signature : au stade « Envoyé pour signature ». */
function contratSignable(array $attrs = []): Contract
{
    $candidate = Candidate::factory()->create([
        'email' => 'apprenti@example.test',
        'date_naissance' => now()->subYears(25),
    ]);

    return Contract::factory()->create(array_merge([
        'candidate_id' => $candidate->id,
        'statut_contrat' => ContractStatut::ManqueSignature,
        'statut_signature' => ContractSignatureStatut::NonSigne,
        'date_debut' => now(),
    ], $attrs));
}

it('compose les signataires par défaut (apprenti, employeur, CFA)', function () {
    $contract = contratSignable();

    $signataires = serviceSimu()->signatairesParDefaut($contract);
    $roles = collect($signataires)->pluck('role');

    expect($roles)->toContain(SignatureService::ROLE_APPRENTI)
        ->and($roles)->toContain(SignatureService::ROLE_EMPLOYEUR)
        ->and($roles)->toContain(SignatureService::ROLE_CFA)
        ->and($roles)->not->toContain(SignatureService::ROLE_REPRESENTANT);
});

it('ajoute le représentant légal si l\'apprenti est mineur', function () {
    $mineur = Candidate::factory()->create([
        'email' => 'mineur@example.test',
        'date_naissance' => now()->subYears(16),
    ]);
    $contract = contratSignable(['candidate_id' => $mineur->id]);

    $roles = collect(serviceSimu()->signatairesParDefaut($contract))->pluck('role');

    expect($roles)->toContain(SignatureService::ROLE_REPRESENTANT);
});

it('envoie une demande de signature et bascule le contrat en « Envoyé »', function () {
    $contract = contratSignable();

    $request = serviceSimu()->envoyer($contract);

    expect($request->statut)->toBe(SignatureRequestStatut::Envoyee)
        ->and($request->external_id)->toStartWith('SIMU-')
        ->and($request->sent_at)->not->toBeNull()
        ->and($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Envoye);
});

it('est idempotent : une demande en cours n\'est pas dupliquée', function () {
    $contract = contratSignable();
    $service = serviceSimu();

    $a = $service->envoyer($contract);
    $b = $service->envoyer($contract->fresh());

    expect($b->id)->toBe($a->id)
        ->and(SignatureRequest::where('contract_id', $contract->id)->count())->toBe(1);
});

it('refuse d\'envoyer un contrat déjà signé', function () {
    $contract = contratSignable(['statut_signature' => ContractSignatureStatut::Signe]);

    serviceSimu()->envoyer($contract);
})->throws(RuntimeException::class);

it('passe partiellement signée quand une seule partie a signé', function () {
    $contract = contratSignable();
    $service = serviceSimu();
    $request = $service->envoyer($contract);

    $service->enregistrerSignature($request, 'apprenti@example.test');

    expect($request->fresh()->statut)->toBe(SignatureRequestStatut::PartiellementSignee)
        ->and($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Envoye);
});

it('signe le contrat et archive la preuve quand toutes les parties ont signé', function () {
    $contract = contratSignable();
    $service = serviceSimu();
    $request = $service->envoyer($contract);

    $service->simulerSignatureComplete($request);

    expect($request->fresh()->statut)->toBe(SignatureRequestStatut::Signee)
        ->and($request->fresh()->completed_at)->not->toBeNull()
        ->and($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Signe)
        ->and($contract->fresh()->statut_contrat)->toBe(ContractStatut::Complet)
        ->and($contract->fresh()->opcoFile()->exists())->toBeTrue()
        ->and($contract->documents()->where('type', DocumentType::Contrat->value)->exists())->toBeTrue();
});

it('signe le contrat et ouvre le dossier OPCO même s\'il n\'était pas « Envoyé pour signature »', function () {
    // Cas réel du bug : contrat encore en amont (jamais passé par « Envoyé »).
    $contract = contratSignable(['statut_contrat' => ContractStatut::EnCours]);
    $service = serviceSimu();
    $request = $service->envoyer($contract);

    $service->simulerSignatureComplete($request);

    $fresh = $contract->fresh();
    expect($fresh->statut_contrat)->toBe(ContractStatut::Complet)
        ->and($fresh->statut_signature)->toBe(ContractSignatureStatut::Signe)
        ->and($fresh->opcoFile()->exists())->toBeTrue();
});

it('finalise via le webhook du prestataire', function () {
    $contract = contratSignable();
    // Le service par défaut de l'app (driver simulation) porte le webhook.
    $request = app(SignatureService::class)->envoyer($contract);

    // Chaque partie signe (callback prestataire), identifiée par son rôle.
    foreach ($request->fresh()->signataires as $s) {
        $this->postJson('/webhooks/signature/simulation', [
            'external_id' => $request->external_id,
            'signataire' => $s['role'],
        ])->assertOk();
    }

    expect($request->fresh()->statut)->toBe(SignatureRequestStatut::Signee)
        ->and($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Signe);
});

it('rejette un webhook pour une enveloppe inconnue', function () {
    $this->postJson('/webhooks/signature/simulation', [
        'external_id' => 'INCONNU-999',
        'signataire' => 'x@example.test',
    ])->assertNotFound();
});

it('désactive la signature avec le driver « none »', function () {
    $service = new SignatureService(new NullSignatureProvider);

    expect($service->estActive())->toBeFalse();

    $service->envoyer(contratSignable());
})->throws(RuntimeException::class);

it('rend la page d\'édition du contrat avec les actions de signature', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['Administrateur']);
    $this->actingAs($admin);

    $contract = contratSignable();

    Livewire\Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->assertOk()
        ->assertActionVisible('envoyerSignature');
});
