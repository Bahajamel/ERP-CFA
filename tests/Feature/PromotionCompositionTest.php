<?php

use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);
});

it('met à jour la composition (ajout + retrait) à l\'édition de la classe', function () {
    $promo = Promotion::factory()->create();
    $initiaux = Candidate::factory()->count(2)->dansClasse($promo)->create();
    $nouveau = Candidate::factory()->create(['formation_visee_id' => $promo->formation_id]);

    // On conserve le 1er, on retire le 2e, on ajoute un nouvel apprenant.
    Livewire::test(EditPromotion::class, ['record' => $promo->getRouteKey()])
        ->fillForm([
            'apprentis_ids' => [$initiaux[0]->id, $nouveau->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($initiaux[0]->promotions()->whereKey($promo->id)->exists())->toBeTrue()
        ->and($initiaux[1]->promotions()->count())->toBe(0)
        ->and($nouveau->promotions()->whereKey($promo->id)->exists())->toBeTrue();
});

it('refuse un apprenant d\'un autre niveau (un 1ère année ne rejoint pas une classe de 2ème année)', function () {
    $formation = Formation::factory()->create();
    $premiereAnnee = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $deuxiemeAnnee = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '2ème année']);
    $apprenti = Candidate::factory()->dansClasse($premiereAnnee)->create(['nom' => 'Rousseau']);

    expect(fn () => $deuxiemeAnnee->composerApprentis([$apprenti->id]))
        ->toThrow(ValidationException::class, 'Rousseau');

    expect($apprenti->promotions()->count())->toBe(1);
});

it('ne propose que la cohorte du niveau (pas les apprenants d\'une autre année)', function () {
    $formation = Formation::factory()->create(['duree_mois' => 24]);
    $premiereAnnee = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    Candidate::factory()->dansClasse($premiereAnnee)->create(['nom' => 'CohortePremiere']);
    Candidate::factory()->create(['nom' => 'NouveauSansClasse', 'formation_visee_id' => $formation->id]);

    // Classe de 2ème année : la cohorte de 1ère année est exclue de sa liste.
    $deuxiemeAnnee = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '2ème année']);
    Livewire::test(EditPromotion::class, ['record' => $deuxiemeAnnee->getRouteKey()])
        ->assertSee('NouveauSansClasse')
        ->assertDontSee('CohortePremiere');

    // Autre classe de 1ère année : la cohorte de 1ère année est proposée.
    $autrePremiere = Promotion::factory()->create([
        'formation_id' => $formation->id, 'libelle' => '1ère année', 'nom' => 'Autre 1A',
    ]);
    Livewire::test(EditPromotion::class, ['record' => $autrePremiere->getRouteKey()])
        ->assertSee('CohortePremiere')
        ->assertSee('NouveauSansClasse');
});

it('refuse un apprenant dont la formation visée diffère de celle de la classe (une seule formation)', function () {
    $promo = Promotion::factory()->create();
    $autreFormation = Formation::factory()->create();
    $apprenti = Candidate::factory()->create([
        'nom' => 'Benali',
        'formation_visee_id' => $autreFormation->id,
    ]);

    expect(fn () => $promo->composerApprentis([$apprenti->id]))
        ->toThrow(ValidationException::class, 'Benali');

    expect($apprenti->promotions()->count())->toBe(0);
});

it('un apprenant sans formation renseignée adopte celle de la classe', function () {
    $promo = Promotion::factory()->create();
    $apprenti = Candidate::factory()->create(['formation_visee_id' => null]);

    $promo->composerApprentis([$apprenti->id]);

    expect($apprenti->promotions()->whereKey($promo->id)->exists())->toBeTrue()
        ->and($apprenti->fresh()->formation_visee_id)->toBe($promo->formation_id);
});

it('affiche le nom complet d\'une classe : formation, année et année scolaire', function () {
    $formation = Formation::factory()->create(['libelle' => 'BTS MCO']);
    $promo = Promotion::factory()->create([
        'formation_id' => $formation->id,
        'libelle' => '1ère année',
        'annee_scolaire' => '2025-2026',
    ]);

    expect($promo->nom_complet)->toBe('BTS MCO — 1ère année (2025-2026)')
        ->and(Promotion::factory()->create(['formation_id' => null, 'annee_scolaire' => null, 'libelle' => 'Groupe A'])->nom_complet)
        ->toBe('Groupe A');
});

it('affiche la liste des classes groupée par formation, triée par année', function () {
    $formation = Formation::factory()->create(['libelle' => 'BTS Test Groupement']);
    Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '2ème année']);
    Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);

    Livewire::test(ListPromotions::class)
        ->assertSuccessful()
        ->assertSee('BTS Test Groupement')
        ->assertSeeInOrder(['1ère année', '2ème année']);
});

it('ne propose dans la liste que les apprenants compatibles avec la formation de la classe', function () {
    $formation = Formation::factory()->create();
    Candidate::factory()->create(['nom' => 'Compatible', 'formation_visee_id' => $formation->id]);
    Candidate::factory()->create(['nom' => 'SansFormation', 'formation_visee_id' => null]);
    Candidate::factory()->create(['nom' => 'Incompatible']); // autre formation (factory)

    $promo = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    Livewire::test(EditPromotion::class, ['record' => $promo->getRouteKey()])
        ->assertSee('Compatible')
        ->assertSee('SansFormation')
        ->assertDontSee('Incompatible');
});
