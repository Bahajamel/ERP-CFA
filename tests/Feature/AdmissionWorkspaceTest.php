<?php

use App\Enums\AdmissionStatut;
use App\Enums\DocumentType;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\Admissions\Tables\AdmissionsTable;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function admissionWorkspaceAdmin(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

/** Crée une admission (statut donné) sans passer par les invariants du contrat. */
function admissionStatut(Candidate $candidate, AdmissionStatut $statut = AdmissionStatut::AVerifier): Admission
{
    $admission = new Admission(['statut' => $statut->value]);
    $admission->candidate_id = $candidate->id;
    $admission->saveQuietly(); // évite l'invariant creating() (contrat requis)

    return $admission;
}

it('rend le workspace admissions avec ses filtres rapides', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());
    admissionStatut(Candidate::factory()->create());

    Livewire::test(ListAdmissions::class)
        ->assertOk()
        ->assertSee('à valider')
        ->assertSee('inscrits')
        ->assertSee('en rupture');
});

it('bascule le filtre rapide et le désactive au second clic', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());

    Livewire::test(ListAdmissions::class)
        ->assertSet('quickScope', null)
        ->call('setQuickScope', 'en_rupture')
        ->assertSet('quickScope', 'en_rupture')
        ->call('setQuickScope', 'en_rupture')
        ->assertSet('quickScope', null);
});

it('sépare les admissions à valider des apprenants inscrits', function () {
    $this->seed(RolePermissionSeeder::class);

    admissionStatut(Candidate::factory()->create(), AdmissionStatut::AVerifier);
    admissionStatut(Candidate::factory()->create(), AdmissionStatut::Valide);
    admissionStatut(Candidate::factory()->create(), AdmissionStatut::Valide);

    $aValider = Admission::query()->whereHas('candidate')
        ->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'a_valider'))->count();
    $inscrits = Admission::query()->whereHas('candidate')
        ->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'inscrits'))->count();

    expect($aValider)->toBe(1)
        ->and($inscrits)->toBe(2);
});

it('utilise pièce d\'identité, CV et carte vitale comme pièces d\'admission', function () {
    expect(Admission::PIECES_OBLIGATOIRES)->toBe([
        DocumentType::PieceIdentite,
        DocumentType::CvCandidat,
        DocumentType::CarteVitale,
    ]);
});

it('n\'affiche le panneau Focus du jour qu\'après sélection', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());
    $admission = admissionStatut(Candidate::factory()->create(['prenom' => 'Sonia', 'nom' => 'Ledoux']));

    Livewire::test(ListAdmissions::class)
        ->assertDontSee('Focus du jour')
        ->set('focusId', $admission->id)
        ->assertSee('Focus du jour')
        ->assertSee('Sonia Ledoux')
        ->call('unfocus')
        ->assertSet('focusId', null)
        ->assertDontSee('Focus du jour');
});
