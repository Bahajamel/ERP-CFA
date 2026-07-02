<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AdminUserSeeder::class);
});

it('connecte directement l\'administrateur via l\'accès rapide de démo', function () {
    $admin = User::where('email', 'admin@cfa-v2s.fr')->first();

    $this->get('/demo/admin')
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
});

it('affiche le bouton d\'accès rapide sur la page de connexion (hors production)', function () {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Accès rapide Admin');
});
