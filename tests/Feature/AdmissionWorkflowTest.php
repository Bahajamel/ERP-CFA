<?php

use App\Enums\AdmissionStatut;
use App\Enums\ChecklistItemStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

function admissionAVerifier(): Admission
{
    $admission = Admission::factory()->create(['statut' => AdmissionStatut::AVerifier]);
    $admission->genererChecklistObligatoire();

    return $admission->refresh();
}

it('génère les pièces obligatoires standard, de façon idempotente', function () {
    $admission = admissionAVerifier();

    expect($admission->items()->count())->toBe(count(Admission::PIECES_OBLIGATOIRES));

    // Rejouer ne crée pas de doublon.
    $admission->genererChecklistObligatoire();
    expect($admission->items()->count())->toBe(count(Admission::PIECES_OBLIGATOIRES));
});

it('ne propose que des pièces pertinentes à l\'admission (pas le CV maître d\'apprentissage)', function () {
    $types = App\Enums\DocumentType::pourAdmission();

    expect($types)
        ->not->toContain(App\Enums\DocumentType::CvMaitreApprentissage)
        ->not->toContain(App\Enums\DocumentType::Contrat)
        ->not->toContain(App\Enums\DocumentType::Cerfa)
        ->not->toContain(App\Enums\DocumentType::Convention)
        ->toContain(App\Enums\DocumentType::PieceIdentite)
        ->toContain(App\Enums\DocumentType::CvCandidat)
        ->toContain(App\Enums\DocumentType::DiplomeBulletins);

    // Le libellé exact « CV maître d'apprentissage » ne doit pas figurer dans les options.
    expect(App\Enums\DocumentType::optionsPour($types))
        ->not->toHaveKey(App\Enums\DocumentType::CvMaitreApprentissage->value);
});

it('interdit la validation tant qu\'une pièce obligatoire manque', function () {
    $admission = admissionAVerifier();

    expect($admission->estComplet())->toBeFalse()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeFalse();

    expect(fn () => $admission->transitionTo(AdmissionStatut::Valide))
        ->toThrow(InvalidTransitionException::class);

    expect($admission->fresh()->statut)->toBe(AdmissionStatut::AVerifier);
});

it('autorise la validation quand toutes les pièces obligatoires sont présentes', function () {
    $admission = admissionAVerifier();
    $admission->items()->update(['statut' => ChecklistItemStatut::Presente->value]);

    expect($admission->estComplet())->toBeTrue()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeTrue();

    $admission->transitionTo(AdmissionStatut::Valide);

    $admission->refresh();
    expect($admission->statut)->toBe(AdmissionStatut::Valide)
        ->and($admission->validated_at)->not->toBeNull()
        ->and($admission->validated_by)->toBe(auth()->id());
});

it('exclut Validé des transitions autorisées tant que le dossier est incomplet', function () {
    $admission = admissionAVerifier();

    $autorisees = $admission->allowedTransitions();
    expect($autorisees)->not->toContain(AdmissionStatut::Valide)
        ->and($autorisees)->toContain(AdmissionStatut::Refuse);

    $admission->items()->update(['statut' => ChecklistItemStatut::Presente->value]);
    expect($admission->allowedTransitions())->toContain(AdmissionStatut::Valide);
});

it('refuse toute transition structurellement interdite (depuis un état terminal)', function () {
    $admission = admissionAVerifier();
    $admission->items()->update(['statut' => ChecklistItemStatut::Presente->value]);
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
