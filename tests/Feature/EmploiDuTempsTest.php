<?php

use App\Filament\Pages\EmploiDuTemps;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Formateur');
    $this->actingAs($user);
});

it('affiche les séances de la semaine pour la classe choisie', function () {
    $formation = Formation::factory()->create(['libelle' => 'CDA Test']);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    Seance::factory()->create([
        'promotion_id' => $classe->id,
        'libelle' => 'Développement web', // la matière vit au niveau de la séance
        'date' => now()->startOfWeek()->addDay()->toDateString(), // mardi de la semaine courante
        'heure_debut' => '09:00',
        'heure_fin' => '12:00',
    ]);

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->assertSuccessful()
        ->assertSee('CDA Test — 1ère année') // le sélecteur liste formation + année
        ->assertSee('Développement web')
        ->assertSee('09:00');
});

it('sépare les emplois du temps par classe : la 2ème année n\'apparaît pas sur la 1ère', function () {
    $formation = Formation::factory()->create();
    $premiere = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $deuxieme = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '2ème année']);

    $lundi = now()->startOfWeek()->toDateString();
    Seance::factory()->create(['promotion_id' => $premiere->id, 'libelle' => 'MatierePremiere', 'date' => $lundi]);
    Seance::factory()->create(['promotion_id' => $deuxieme->id, 'libelle' => 'MatiereDeuxieme', 'date' => $lundi]);

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $premiere->id])
        ->assertSee('MatierePremiere')
        ->assertDontSee('MatiereDeuxieme');
});

it('deux classes de même libellé mais d\'années scolaires différentes ne se mélangent pas', function () {
    $formation = Formation::factory()->create();
    $a = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année', 'annee_scolaire' => '2025-2026']);
    $b = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année', 'annee_scolaire' => '2026-2027']);

    $lundi = now()->startOfWeek()->toDateString();
    Seance::factory()->create(['promotion_id' => $a->id, 'libelle' => 'SeanceAnneeA', 'date' => $lundi]);
    Seance::factory()->create(['promotion_id' => $b->id, 'libelle' => 'SeanceAnneeB', 'date' => $lundi]);

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $b->id])
        ->assertSee('SeanceAnneeB')
        ->assertDontSee('SeanceAnneeA');
});

it('navigue de semaine en semaine', function () {
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    Seance::factory()->create([
        'promotion_id' => $classe->id,
        'libelle' => 'SemaineProchaine',
        'date' => now()->startOfWeek()->addWeek()->toDateString(), // lundi prochain
    ]);

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->assertDontSee('SemaineProchaine')
        ->call('semaineSuivante')
        ->assertSee('SemaineProchaine')
        ->call('semaineCourante')
        ->assertDontSee('SemaineProchaine');
});
