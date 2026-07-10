<?php

use App\Filament\Pages\ParametresCfa;
use App\Models\CfaProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminCfa(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

it('expose un profil CFA unique (singleton)', function () {
    $a = CfaProfile::current();
    $b = CfaProfile::current();

    expect($a->id)->toBe($b->id)
        ->and(CfaProfile::count())->toBe(1);
});

it('réserve les Paramètres CFA à la direction et à l\'administrateur', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['Administrateur']);
    $this->actingAs($admin);
    expect(ParametresCfa::canAccess())->toBeTrue();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(ParametresCfa::canAccess())->toBeFalse();
});

it('affiche la page Paramètres CFA sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);

    Livewire::actingAs(adminCfa())
        ->test(ParametresCfa::class)
        ->assertOk();
});

it('enregistre les champs du profil CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    Livewire::actingAs(adminCfa())
        ->test(ParametresCfa::class)
        ->set('data.nom', 'CFA V2S')
        ->set('data.ville', 'Paris')
        ->set('data.theme_defaut', 'premium')
        ->call('save')
        ->assertNotified();

    $profile = CfaProfile::current();
    expect($profile->nom)->toBe('CFA V2S')
        ->and($profile->ville)->toBe('Paris')
        ->and($profile->theme_defaut)->toBe('premium');
});
