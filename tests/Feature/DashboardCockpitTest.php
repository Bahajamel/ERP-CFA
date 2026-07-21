<?php

use App\Enums\ContractSignatureStatut;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PrioritesDuJourWidget;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cockpitAdmin(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

it('rend le widget « Priorités du jour » sans erreur (état à jour)', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitAdmin());

    Livewire::test(PrioritesDuJourWidget::class)
        ->assertOk()
        ->assertSee('Priorités du jour')
        ->assertSee('Tout est à jour');
});

it('remonte les contrats à faire signer dans les priorités', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitAdmin());

    Contract::factory()->create(['statut_signature' => ContractSignatureStatut::Envoye]);

    Livewire::test(PrioritesDuJourWidget::class)
        ->assertOk()
        ->assertSee('contrat à faire signer');
});

it('rend le dashboard complet avec le cockpit', function () {
    $this->seed(RolePermissionSeeder::class);
    // Le MFA est obligatoire pour les admins : on l'active pour accéder au panel
    // sans être redirigé vers sa mise en place.
    $admin = cockpitAdmin();
    $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());
    $this->actingAs($admin);

    // En multi-tenant, le dashboard vit sous /admin/{cfa} : on cible l'URL du tenant courant.
    $this->get(Dashboard::getUrl())->assertOk();
});
