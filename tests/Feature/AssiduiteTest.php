<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Models\Candidate;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Émarge l'unique apprenti d'une promo sur une séance donnée. */
function emarger(Seance $seance, Candidate $c, PresenceStatut $statut): void
{
    $seance->presences()->where('candidate_id', $c->id)->update(['statut' => $statut]);
}

it('calcule l\'assiduité d\'un apprenti (présents / renseignés)', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    $s1 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-01']);
    $s2 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-08']);

    emarger($s1, $c, PresenceStatut::Present);
    emarger($s2, $c, PresenceStatut::AbsentInjustifie);

    $a = $c->assiduite();

    expect($a['renseignees'])->toBe(2)
        ->and($a['presents'])->toBe(1)
        ->and($a['absences_injustifiees'])->toBe(1)
        ->and($a['taux'])->toBe(50);
});

it('restreint le calcul d\'assiduité à la période', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    $s1 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-01']);
    $s2 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-10-01']);
    emarger($s1, $c, PresenceStatut::Present);
    emarger($s2, $c, PresenceStatut::AbsentInjustifie);

    // Sur septembre seulement : 1 séance, présent → 100 %
    expect($c->assiduite('2026-09-01', '2026-09-30')['taux'])->toBe(100);
});

it('affiche la page assiduité aux rôles scolarité', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create(['nom' => 'Traore', 'prenom' => 'Salif']);

    Livewire::test(Assiduite::class)
        ->assertSuccessful()
        ->assertSee('Salif Traore');
});
