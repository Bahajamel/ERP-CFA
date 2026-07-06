<?php

use App\Enums\AdmissionStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

/** Dossier de pré-admission d'un candidat SANS CV (créé automatiquement). */
function admissionSansCv(): Admission
{
    return Candidate::factory()->create()->admission;
}

/** Dossier de pré-admission d'un candidat AVEC un CV attaché. */
function admissionAvecCv(): Admission
{
    Storage::fake('public');
    $candidate = Candidate::factory()->create();
    $candidate->addMediaFromString("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF")
        ->usingFileName('cv.pdf')
        ->toMediaCollection('cv');

    return $candidate->admission;
}

it('crée automatiquement un dossier de pré-admission à la création du candidat', function () {
    $candidate = Candidate::factory()->create();

    expect($candidate->admission)->not->toBeNull()
        ->and($candidate->admission->statut)->toBe(AdmissionStatut::AVerifier);
});

it('n\'ouvre qu\'un seul dossier par candidat (pas de doublon)', function () {
    $candidate = Candidate::factory()->create();

    expect(Admission::where('candidate_id', $candidate->id)->count())->toBe(1);
});

it('interdit la validation tant que le CV est manquant', function () {
    $admission = admissionSansCv();

    expect($admission->estComplet())->toBeFalse()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeFalse();

    expect(fn () => $admission->transitionTo(AdmissionStatut::Valide))
        ->toThrow(InvalidTransitionException::class);

    expect($admission->fresh()->statut)->toBe(AdmissionStatut::AVerifier);
});

it('autorise la validation quand le CV est fourni', function () {
    $admission = admissionAvecCv();

    expect($admission->estComplet())->toBeTrue()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeTrue();

    $admission->transitionTo(AdmissionStatut::Valide);

    $admission->refresh();
    expect($admission->statut)->toBe(AdmissionStatut::Valide)
        ->and($admission->validated_at)->not->toBeNull()
        ->and($admission->validated_by)->toBe(auth()->id());
});

it('exclut Validé des transitions autorisées tant que le CV manque', function () {
    $admission = admissionSansCv();

    $autorisees = $admission->allowedTransitions();
    expect($autorisees)->not->toContain(AdmissionStatut::Valide)
        ->and($autorisees)->toContain(AdmissionStatut::Refuse);
});

it('inclut Validé dans les transitions dès que le CV est fourni', function () {
    $admission = admissionAvecCv();

    expect($admission->allowedTransitions())->toContain(AdmissionStatut::Valide);
});

it('refuse toute transition structurellement interdite (depuis un état terminal)', function () {
    $admission = admissionAvecCv();
    $admission->transitionTo(AdmissionStatut::Valide);

    expect($admission->allowedTransitions())->toBe([]);
    expect(fn () => $admission->transitionTo(AdmissionStatut::Refuse))
        ->toThrow(InvalidTransitionException::class);
});

it('réserve les admissions aux rôles autorisés', function () {
    $admission = User::factory()->create();
    $admission->syncRoles(['Admission']);
    $this->actingAs($admission);
    expect(AdmissionResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create();
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(AdmissionResource::canAccess())->toBeFalse();
});
