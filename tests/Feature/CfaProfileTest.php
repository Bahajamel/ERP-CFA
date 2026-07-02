<?php

use App\Filament\Pages\ParametresCfa;
use App\Models\CfaProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
