<?php

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\Pages\EditSeance;
use App\Filament\Resources\Seances\RelationManagers\PresencesRelationManager;
use App\Models\Candidate;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function promotionAvecApprentis(int $n = 3): Promotion
{
    $promo = Promotion::factory()->create();
    Candidate::factory()->count($n)->create(['promotion_id' => $promo->id]);

    return $promo;
}

it('génère une présence « non renseigné » par apprenti à la création de la séance', function () {
    $promo = promotionAvecApprentis(3);

    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);

    expect($seance->presences()->count())->toBe(3)
        ->and($seance->presences()->where('statut', PresenceStatut::NonRenseigne->value)->count())->toBe(3);
});

it('calcule le taux de présence (présents / renseignés)', function () {
    $promo = promotionAvecApprentis(4);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);

    $presences = $seance->presences()->get();
    $presences[0]->update(['statut' => PresenceStatut::Present]);
    $presences[1]->update(['statut' => PresenceStatut::Retard]);   // compte comme présent
    $presences[2]->update(['statut' => PresenceStatut::AbsentInjustifie]);
    // le 4e reste « non renseigné » → hors calcul

    expect($seance->tauxPresence())->toBe(67) // 2 présents / 3 renseignés
        ->and($seance->aDesPresencesNonRenseignees())->toBeTrue();
});

it('n\'a plus de présences non renseignées une fois toutes émargées', function () {
    $promo = promotionAvecApprentis(2);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    expect($seance->aDesPresencesNonRenseignees())->toBeFalse()
        ->and($seance->tauxPresence())->toBe(100);
});

it('affiche l\'émargement de la séance', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Formateur');
    $this->actingAs($user);

    $promo = promotionAvecApprentis(1);
    $candidate = Candidate::where('promotion_id', $promo->id)->first();
    $candidate->update(['nom' => 'Ouedraogo', 'prenom' => 'Awa']);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);

    Livewire::test(PresencesRelationManager::class, [
        'ownerRecord' => $seance,
        'pageClass' => EditSeance::class,
    ])
        ->assertSuccessful()
        ->assertSee('Awa Ouedraogo');
});
