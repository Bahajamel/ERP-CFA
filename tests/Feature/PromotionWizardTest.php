<?php

use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Création d'une promotion (session de formation) via l'assistant en 3 étapes.
 * La promotion sert de modèle : sa configuration est héritée par le contrat.
 */
function adminPromotion(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function donneesPromotion(Formation $formation, array $overrides = []): array
{
    return array_merge([
        'nom' => 'TP EPR E-LEARNING 12 MOIS JUIN 2026',
        'formation_id' => $formation->id,
        'libelle' => '1ère année',
        'annee_scolaire' => '2025-2026',
        'lieu_formation_numero' => '45',
        'lieu_formation' => 'Rue de Jouy',
        'lieu_formation_code_postal' => '92370',
        'lieu_formation_ville' => 'Chaville',
        'lieu_formation_pays' => 'France',

        'type_contrat' => TypeContrat::Apprentissage->value,
        'modalite_suivi' => ModaliteSuivi::Distance->value,
        'duree_formation_heures' => 455,
        'heures_elearning' => 400,
        'heures_classe_virtuelle' => 55,
        'reste_a_charge_zero' => 1,
        'date_debut' => '2026-06-01',
        'date_fin' => '2027-05-31',
        'frais_hebergement' => 0,
        'frais_restauration' => 0,
        'frais_equipement' => 1,
        'type_equipement' => 'informatique',
        'frais_mobilite' => 0,
    ], $overrides);
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminPromotion());
});

it('crée une promotion complète à la soumission finale', function () {
    $formation = Formation::factory()->create(['libelle' => 'EMPLOYE POLYVALENT DE RESTAURATION']);

    Livewire::test(CreatePromotion::class)
        ->fillForm(donneesPromotion($formation))
        ->call('create')
        ->assertHasNoFormErrors();

    $promo = Promotion::sole();

    expect($promo->nom)->toBe('TP EPR E-LEARNING 12 MOIS JUIN 2026')
        ->and($promo->formation_id)->toBe($formation->id)
        ->and($promo->libelle)->toBe('1ère année')
        ->and($promo->type_contrat)->toBe(TypeContrat::Apprentissage)
        ->and($promo->modalite_suivi)->toBe(ModaliteSuivi::Distance)
        ->and($promo->duree_formation_heures)->toBe(455)
        ->and($promo->reste_a_charge_zero)->toBeTrue()
        ->and($promo->frais_equipement)->toBeTrue()
        ->and($promo->type_equipement)->toBe('informatique')
        ->and($promo->nom_complet)->toBe('TP EPR E-LEARNING 12 MOIS JUIN 2026');
});

it('ne crée aucune promotion tant que la soumission finale n’est pas déclenchée', function () {
    $formation = Formation::factory()->create();

    Livewire::test(CreatePromotion::class)->fillForm(donneesPromotion($formation));

    expect(Promotion::count())->toBe(0);
});

it('autorise plusieurs promotions par formation (sessions mensuelles) mais refuse l’homonymie', function () {
    $formation = Formation::factory()->create();

    // JUIN.
    Livewire::test(CreatePromotion::class)
        ->fillForm(donneesPromotion($formation, ['nom' => 'TP EPR 12 MOIS JUIN 2026']))
        ->call('create')
        ->assertHasNoFormErrors();

    // JUILLET — même formation, même niveau, nom différent : autorisé.
    Livewire::test(CreatePromotion::class)
        ->fillForm(donneesPromotion($formation, ['nom' => 'TP EPR 12 MOIS JUILLET 2026']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Promotion::where('formation_id', $formation->id)->count())->toBe(2);

    // Reprise du nom de JUIN : refusé (homonymie dans la même formation).
    Livewire::test(CreatePromotion::class)
        ->fillForm(donneesPromotion($formation, ['nom' => 'TP EPR 12 MOIS JUIN 2026']))
        ->call('create')
        ->assertHasFormErrors(['nom']);

    expect(Promotion::where('formation_id', $formation->id)->count())->toBe(2);
});

it('exige le nom et la formation', function () {
    $formation = Formation::factory()->create();

    Livewire::test(CreatePromotion::class)
        ->fillForm(donneesPromotion($formation, ['nom' => null]))
        ->call('create')
        ->assertHasFormErrors(['nom' => 'required']);

    expect(Promotion::count())->toBe(0);
});

it('fait hériter la configuration de la promotion au contrat qui la choisit', function () {
    $formation = Formation::factory()->create();
    $promo = Promotion::factory()->create([
        'formation_id' => $formation->id,
        'nom' => 'Session héritée',
        'type_contrat' => TypeContrat::Apprentissage->value,
        'modalite_suivi' => ModaliteSuivi::Distance->value,
        'duree_formation_heures' => 455,
        'heures_elearning' => 400,
        'heures_classe_virtuelle' => 55,
        'reste_a_charge_zero' => true,
        'date_debut' => '2026-06-01',
        'date_fin' => '2027-05-31',
    ]);

    Livewire::test(CreateContract::class)
        ->set('data.formation_id', $formation->id)
        ->set('data.promotion_id', $promo->id)
        ->assertSet('data.modalite_suivi', ModaliteSuivi::Distance->value)
        ->assertSet('data.duree_formation_heures', 455)
        ->assertSet('data.heures_elearning', 400)
        ->assertSet('data.date_debut', '2026-06-01');
});
