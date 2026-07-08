<?php

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Admissions\Pages\EditAdmission;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\User;
use App\Support\AdresseBan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function preadmUser(string $role = 'Admission'): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles([$role]);

    return $u;
}

function candidatAvecCvMedia(): Candidate
{
    Storage::fake('public');
    $candidate = Candidate::factory()->create();
    $candidate->addMediaFromString("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF")
        ->usingFileName('cv.pdf')
        ->toMediaCollection('cv');

    return $candidate;
}

it('rend le CV du candidat accessible depuis son admission officielle', function () {
    $candidate = candidatAvecCvMedia();
    $admission = Admission::factory()->create(['candidate_id' => $candidate->id]);

    // Le CV posé côté candidat est disponible côté admission via la relation.
    expect($admission->candidate->hasCv())->toBeTrue()
        ->and($admission->cvManquant())->toBeFalse()
        ->and($candidate->cvUrl())->not->toBeNull();
});

it('détecte le CV qu\'il soit en média « cv » ou en document GED de type CV', function () {
    Storage::fake('public');

    // Source 2 : un document GED de type CV avec un fichier (pas de média « cv »).
    $candidate = Candidate::factory()->create();
    expect($candidate->hasCv())->toBeFalse();

    $doc = $candidate->documents()->create([
        'type' => DocumentType::CvCandidat->value,
        'statut' => DocumentStatut::Recu->value,
        'uploaded_by' => null,
    ]);
    $doc->addMediaFromString("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF")
        ->usingFileName('cv.pdf')
        ->toMediaCollection('fichier');

    expect($candidate->fresh()->hasCv())->toBeTrue();
});

it('n\'expose aucune gestion documentaire (checklist) dans le module Admissions', function () {
    // Les pièces vivent sur la fiche candidat : pas de RelationManager côté
    // admission (donc pas de « Ajouter une pièce » ni de checklist).
    expect(AdmissionResource::getRelations())->toBe([]);
});

it('gère les périodes de disponibilité du candidat', function () {
    $candidate = Candidate::factory()->create();
    $candidate->availabilities()->create([
        'type' => App\Enums\AvailabilityType::Disponible->value,
        'immediate' => true,
    ]);

    expect($candidate->availabilities()->count())->toBe(1)
        ->and($candidate->availabilities->first()->libelle())->toContain('immédiat');
});

it('rend la page de création candidat (adresse, CV, disponibilités) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(preadmUser('Commercial'));

    Livewire\Livewire::test(CreateCandidate::class)->assertOk();
});

it('rend la page de l\'admission officielle (parcours + dossier) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(preadmUser('Admission'));

    $candidate = candidatAvecCvMedia();
    $admission = Admission::factory()->create(['candidate_id' => $candidate->id]);

    Livewire\Livewire::test(EditAdmission::class, ['record' => $admission->getKey()])
        ->assertOk();
});

it('renvoie une liste vide pour une recherche d\'adresse trop courte (fallback manuel)', function () {
    expect(app(AdresseBan::class)->options('ab'))->toBe([]);
    expect(AdresseBan::decode(null))->toBeNull();
});
