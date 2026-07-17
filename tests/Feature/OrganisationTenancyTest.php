<?php

use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rattache un utilisateur à une ou plusieurs organisations', function () {
    $user = User::factory()->create();
    $user->organisations()->detach(); // repart d'un état propre (le CFA de test s'auto-rattache)
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    $user->organisations()->attach([$cfaA->id, $cfaB->id]);

    expect($user->refresh()->organisations)->toHaveCount(2)
        ->and($cfaA->users)->toHaveCount(1);
});

it('ne propose dans le sélecteur de tenant que les organisations actives du membre', function () {
    $user = User::factory()->create();
    $user->organisations()->detach();
    $active = Organisation::factory()->create(['actif' => true]);
    $inactive = Organisation::factory()->create(['actif' => false]);
    Organisation::factory()->create(); // autre CFA, non membre

    $user->organisations()->attach([$active->id, $inactive->id]);

    $tenants = $user->getTenants(Filament::getPanel('admin'));

    expect($tenants->pluck('id')->all())->toBe([$active->id]);
});

it('autorise l’accès à un CFA seulement si membre et CFA actif', function () {
    $user = User::factory()->create();
    $membre = Organisation::factory()->create(['actif' => true]);
    $membreInactif = Organisation::factory()->create(['actif' => false]);
    $nonMembre = Organisation::factory()->create(['actif' => true]);

    $user->organisations()->attach([$membre->id, $membreInactif->id]);

    expect($user->canAccessTenant($membre))->toBeTrue()
        ->and($user->canAccessTenant($membreInactif))->toBeFalse()
        ->and($user->canAccessTenant($nonMembre))->toBeFalse();
});
