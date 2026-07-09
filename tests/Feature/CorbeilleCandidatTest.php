<?php

use App\Enums\CandidateStatut;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Corbeille\CorbeilleResource;
use App\Filament\Resources\Corbeille\Pages\ListCorbeille;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);
});

it('archive un candidat avec un motif et l\'auteur, sans le supprimer réellement', function () {
    $candidate = Candidate::factory()->create();

    $candidate->archiver('Doublon manifeste');

    expect($candidate->trashed())->toBeTrue()
        ->and($candidate->motif_suppression)->toBe('Doublon manifeste')
        ->and($candidate->deleted_by)->toBe(auth()->id())
        ->and(Candidate::withTrashed()->whereKey($candidate->id)->exists())->toBeTrue();
});

it('retire le candidat archivé de toutes les listes (plus de ligne orpheline)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    $need = Need::factory()->create();
    $matching = Matching::factory()->create(['candidate_id' => $candidate->id, 'need_id' => $need->id]);

    // Avant : la ligne matching est visible.
    expect(Matching::whereHas('candidate')->count())->toBe(1);

    $candidate->archiver('Candidature annulée');

    // Après : la ligne existe toujours en base mais n'est plus listée.
    expect(Matching::whereHas('candidate')->count())->toBe(0)
        ->and(Matching::whereKey($matching->id)->exists())->toBeTrue();
});

it('restaure un candidat et efface le motif (ses lignes réapparaissent)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    $need = Need::factory()->create();
    Matching::factory()->create(['candidate_id' => $candidate->id, 'need_id' => $need->id]);

    $candidate->archiver('Erreur de saisie');
    expect(Matching::whereHas('candidate')->count())->toBe(0);

    $candidate->restaurer();

    expect($candidate->fresh()->trashed())->toBeFalse()
        ->and($candidate->fresh()->motif_suppression)->toBeNull()
        ->and(Matching::whereHas('candidate')->count())->toBe(1);
});

it('calcule les jours restants avant purge (~30 j à la suppression)', function () {
    $candidate = Candidate::factory()->create();
    $candidate->archiver('Test');

    expect($candidate->joursAvantPurge())->toBeGreaterThanOrEqual(29)
        ->and($candidate->joursAvantPurge())->toBeLessThanOrEqual(30);
});

it('purge définitivement les candidats en corbeille depuis plus de 30 jours (et leurs dossiers)', function () {
    $ancien = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    $need = Need::factory()->create();
    $matching = Matching::factory()->create(['candidate_id' => $ancien->id, 'need_id' => $need->id]);
    $ancien->archiver('Vieux dossier');
    // Antidater la suppression au-delà du délai de rétention.
    $ancien->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    $recent = Candidate::factory()->create();
    $recent->archiver('Récent');

    $this->artisan('candidats:purger-corbeille')->assertSuccessful();

    // L'ancien (et son matching via cascade) est effacé ; le récent reste.
    expect(Candidate::withTrashed()->whereKey($ancien->id)->exists())->toBeFalse()
        ->and(Matching::whereKey($matching->id)->exists())->toBeFalse()
        ->and(Candidate::withTrashed()->whereKey($recent->id)->exists())->toBeTrue();
});

it('exige un motif de suppression dans l\'action de la table candidats', function () {
    $candidate = Candidate::factory()->create();

    Livewire::test(ListCandidates::class)
        ->callTableAction('supprimer', $candidate, data: [])
        ->assertHasTableActionErrors(['motif_suppression' => ['required']]);

    expect($candidate->fresh()->trashed())->toBeFalse();
});

it('archive via l\'action de table quand le motif est fourni', function () {
    $candidate = Candidate::factory()->create();

    Livewire::test(ListCandidates::class)
        ->callTableAction('supprimer', $candidate, data: ['motif_suppression' => 'Doublon'])
        ->assertHasNoTableActionErrors();

    expect($candidate->fresh()->trashed())->toBeTrue()
        ->and($candidate->fresh()->motif_suppression)->toBe('Doublon');
});

it('la corbeille ne liste que les candidats supprimés et propose la restauration', function () {
    $actif = Candidate::factory()->create(['nom' => 'Actif', 'prenom' => 'Alice']);
    $supprime = Candidate::factory()->create(['nom' => 'Supprime', 'prenom' => 'Bob']);
    $supprime->archiver('Motif test');

    Livewire::test(ListCorbeille::class)
        ->assertCanSeeTableRecords([$supprime])
        ->assertCanNotSeeTableRecords([$actif])
        ->assertSee('Bob Supprime')
        ->callTableAction('restaurer', $supprime)
        ->assertHasNoTableActionErrors();

    expect($supprime->fresh()->trashed())->toBeFalse();
});

it('réserve la corbeille aux profils ayant accès aux candidats', function () {
    expect(CorbeilleResource::canAccess())->toBeTrue();

    $sansAcces = User::factory()->create(['is_active' => true]);
    $this->actingAs($sansAcces);

    expect(CorbeilleResource::canAccess())->toBeFalse();
});
