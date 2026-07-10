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

/** Crée une admission « À vérifier » sans passer par les invariants du contrat. */
function admissionAVerifier(Candidate $candidate): Admission
{
    $admission = new Admission(['statut' => AdmissionStatut::AVerifier->value]);
    $admission->candidate_id = $candidate->id;
    $admission->saveQuietly(); // évite l'invariant creating() (contrat requis)

    return $admission;
}

it('rend le workspace admissions avec ses filtres rapides', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());
    admissionAVerifier(Candidate::factory()->create());

    Livewire::test(ListAdmissions::class)
        ->assertOk()
        ->assertSee('dossiers à vérifier')
        ->assertSee('prêtes à valider')
        ->assertSee('pièces manquantes');
});

it('bascule le filtre rapide et le désactive au second clic', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());

    Livewire::test(ListAdmissions::class)
        ->assertSet('quickScope', null)
        ->call('setQuickScope', 'pieces_manquantes')
        ->assertSet('quickScope', 'pieces_manquantes')
        ->call('setQuickScope', 'pieces_manquantes')
        ->assertSet('quickScope', null);
});

it('sépare les admissions prêtes de celles avec pièces manquantes', function () {
    $this->seed(RolePermissionSeeder::class);

    // Candidat complet : les 3 pièces obligatoires présentes.
    $complet = Candidate::factory()->create();
    foreach (Admission::PIECES_OBLIGATOIRES as $type) {
        $complet->documents()->create(['type' => $type->value, 'nom_fichier' => $type->getLabel()]);
    }
    admissionAVerifier($complet);

    // Candidat incomplet : diplôme/bulletins manquant.
    $incomplet = Candidate::factory()->create();
    $incomplet->documents()->create(['type' => DocumentType::PieceIdentite->value, 'nom_fichier' => 'ID']);
    $incomplet->documents()->create(['type' => DocumentType::CvCandidat->value, 'nom_fichier' => 'CV']);
    admissionAVerifier($incomplet);

    $pretes = Admission::query()->whereHas('candidate')
        ->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'pretes'))->count();
    $manquantes = Admission::query()->whereHas('candidate')
        ->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'pieces_manquantes'))->count();

    expect($pretes)->toBe(1)
        ->and($manquantes)->toBe(1);
});

it('n\'affiche le panneau Focus du jour qu\'après sélection', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(admissionWorkspaceAdmin());
    $admission = admissionAVerifier(Candidate::factory()->create(['prenom' => 'Sonia', 'nom' => 'Ledoux']));

    Livewire::test(ListAdmissions::class)
        ->assertDontSee('Focus du jour')
        ->set('focusId', $admission->id)
        ->assertSee('Focus du jour')
        ->assertSee('Sonia Ledoux')
        ->call('unfocus')
        ->assertSet('focusId', null)
        ->assertDontSee('Focus du jour');
});
