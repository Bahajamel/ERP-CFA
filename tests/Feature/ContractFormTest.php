<?php

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('monte le formulaire de création de contrat (CERFA + champs requis) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administratif']);
    $this->actingAs($user);

    Livewire::test(CreateContract::class)->assertSuccessful();
});
