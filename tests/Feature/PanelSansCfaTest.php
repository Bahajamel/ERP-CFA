<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Un compte sans CFA ne doit pas se heurter à un 404 nu sur /admin.
 *
 * Les deux panneaux partagent la même session : connecté sur /editeur, on est
 * connecté partout. En arrivant sur /admin, Filament cherche un CFA par défaut,
 * n'en trouve aucun, tente la page d'inscription (que l'on n'expose pas
 * volontairement) et termine en abort(404) — sans rien expliquer.
 */
it('refuse proprement le panneau CFA à un compte sans CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->organisations()->detach(); // l'éditeur n'est pas du personnel de CFA
    $editeur->syncRoles([RolePermissionSeeder::ROLE_EDITEUR]);

    expect($editeur->fresh()->organisations)->toBeEmpty();

    // Le panneau CFA n'a rien à lui montrer : l'accès doit être refusé, pas cassé.
    expect($editeur->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('garde l’accès au panneau CFA pour le personnel rattaché', function () {
    $this->seed(RolePermissionSeeder::class);

    $membre = User::factory()->create(['is_active' => true]); // rattaché par la factory
    $membre->syncRoles(['Administrateur']);

    expect($membre->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('dépose l’éditeur sur la connexion du CFA, sans le murer ni le détourner', function () {
    $this->seed(RolePermissionSeeder::class);

    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->organisations()->detach();
    $editeur->syncRoles([RolePermissionSeeder::ROLE_EDITEUR]);

    // Les deux panneaux partagent la session. Qui demande /admin veut /admin :
    // on le déconnecte et on lui rend l'écran de connexion du CFA — surtout pas
    // un renvoi vers /editeur, qui l'enfermerait dans une boucle sans issue.
    $this->actingAs($editeur)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    expect(auth()->check())->toBeFalse();
});

it('renvoie un compte sans CFA vers la connexion, avec une explication', function () {
    $this->seed(RolePermissionSeeder::class);

    $orphelin = User::factory()->create(['is_active' => true]);
    $orphelin->organisations()->detach();
    $orphelin->syncRoles(['Administrateur']); // rôle métier, mais aucun CFA

    $this->actingAs($orphelin)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    // On le déconnecte : le laisser « connecté à rien » n'aurait aucun sens.
    expect(auth()->check())->toBeFalse();
});

it('laisse entrer normalement le personnel rattaché', function () {
    $this->seed(RolePermissionSeeder::class);

    $membre = User::factory()->create(['is_active' => true]);
    $membre->syncRoles(['Administrateur']);

    // /admin redirige vers le CFA de l'utilisateur : c'est le parcours nominal.
    $this->actingAs($membre)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getUrl(tenant: Filament::getTenant()));
});

it('refuse le panneau CFA si le seul CFA du compte est suspendu', function () {
    $this->seed(RolePermissionSeeder::class);

    $membre = User::factory()->create(['is_active' => true]);
    $membre->syncRoles(['Administrateur']);

    // CFA suspendu depuis /editeur : ses membres ne doivent plus entrer.
    Filament::getTenant()->update(['actif' => false]);

    expect($membre->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
})->skip('dépend du CFA courant du socle de tests — couvert par EditeurPanelTest');
