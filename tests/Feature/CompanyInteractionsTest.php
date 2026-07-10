<?php

use App\Enums\InteractionType;
use App\Filament\RelationManagers\InteractionsRelationManager;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\Interaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('classe les interactions de la plus récente à la plus ancienne', function () {
    $company = Company::factory()->create();

    $ancienne = Interaction::factory()->for($company, 'interactable')
        ->create(['date_interaction' => now()->subMonth()]);
    $recente = Interaction::factory()->for($company, 'interactable')
        ->create(['date_interaction' => now()->subDay()]);

    expect($company->interactions()->pluck('id')->all())
        ->toBe([$recente->id, $ancienne->id]);
});

it('expose la prochaine relance planifiée par l\'interaction la plus récente', function () {
    $company = Company::factory()->create();

    Interaction::factory()->for($company, 'interactable')
        ->create(['date_interaction' => now()->subMonth()]); // sans relance
    Interaction::factory()->for($company, 'interactable')->avecRelance(now()->addDays(3)->toDateString())
        ->create(['date_interaction' => now()->subDay()]);

    $relance = $company->prochaineRelance();

    expect($relance)->not->toBeNull()
        ->and($relance->prochaine_action_le->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('renvoie null comme relance quand aucune action n\'est planifiée', function () {
    $company = Company::factory()->create();
    Interaction::factory()->for($company, 'interactable')->create();

    expect($company->prochaineRelance())->toBeNull();
});

it('le scope relanceDue ne retient que les relances échues', function () {
    $company = Company::factory()->create();

    Interaction::factory()->for($company, 'interactable')->avecRelance(now()->subDay()->toDateString())->create();
    Interaction::factory()->for($company, 'interactable')->avecRelance(now()->addWeek()->toDateString())->create();
    Interaction::factory()->for($company, 'interactable')->create(); // sans relance

    expect(Interaction::query()->relanceDue()->count())->toBe(1);
});

it('consigne une interaction via le gestionnaire et renseigne l\'auteur', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $company = Company::factory()->create();

    Livewire::test(InteractionsRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => EditCompany::class,
    ])
        ->callTableAction('create', data: [
            'type' => InteractionType::Rdv->value,
            'date_interaction' => now()->toDateString(),
            'resume' => 'Point sur les besoins de recrutement 2026.',
            'prochaine_action' => 'Envoyer 3 profils',
            'prochaine_action_le' => now()->addDays(5)->toDateString(),
        ]);

    $interaction = $company->interactions()->firstOrFail();

    expect($interaction->type)->toBe(InteractionType::Rdv)
        ->and($interaction->user_id)->toBe($user->id)
        ->and($interaction->prochaine_action)->toBe('Envoyer 3 profils');
});
