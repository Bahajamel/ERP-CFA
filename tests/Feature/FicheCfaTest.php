<?php

use App\Filament\Pages\Tenancy\ProfilCfa;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * La Fiche du CFA — identité complète du CFA courant.
 *
 * Remplace la page « Paramètres CFA », classée sous la génération de livrables
 * alors que ces champs impriment les CERFA, conventions et bulletins. Reprend
 * ses tests, sauf « expose un profil CFA unique (singleton) », qui gardait le
 * bug : il asseyait qu'il n'existe qu'UNE identité pour toute la plateforme.
 */
function utilisateurAvecRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('réserve la Fiche du CFA à la direction et à l’administrateur', function () {
    $tenant = Filament::getTenant();

    // Ces champs impriment le SIRET et le représentant légal sur des documents
    // déposés à l'OPCO et à l'État : un commercial n'y touche pas.
    $this->actingAs(utilisateurAvecRole('Administrateur'));
    expect(ProfilCfa::canView($tenant))->toBeTrue();

    $this->actingAs(utilisateurAvecRole('Direction'));
    expect(ProfilCfa::canView($tenant))->toBeTrue();

    $this->actingAs(utilisateurAvecRole('Commercial'));
    expect(ProfilCfa::canView($tenant))->toBeFalse();
});

it('affiche la Fiche du CFA sans erreur', function () {
    Livewire::actingAs(utilisateurAvecRole('Administrateur'))
        ->test(ProfilCfa::class)
        ->assertOk();
});

it('enregistre l’identité du CFA', function () {
    Livewire::actingAs(utilisateurAvecRole('Administrateur'))
        ->test(ProfilCfa::class)
        ->fillForm([
            'nom' => 'CFA V2S',
            'raison_sociale' => 'V2S FORMATION',
            'siret' => '12345678900012',
            'ville' => 'Paris',
            'theme_defaut' => 'premium',
            // fillForm remplace tout l'état : les préférences obligatoires
            // doivent être fournies, sinon la validation les réclame.
            'format_defaut' => 'pdf',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cfa = Filament::getTenant()->fresh();

    expect($cfa->nom)->toBe('CFA V2S')
        ->and($cfa->raison_sociale)->toBe('V2S FORMATION')
        ->and($cfa->siret)->toBe('12345678900012')
        ->and($cfa->ville)->toBe('Paris')
        ->and($cfa->theme_defaut)->toBe('premium');
});

it('n’écrit l’identité que sur le CFA courant', function () {
    $autre = Organisation::factory()->create(['nom' => 'Autre CFA', 'siret' => '99999999900099']);

    Livewire::actingAs(utilisateurAvecRole('Administrateur'))
        ->test(ProfilCfa::class)
        ->fillForm([
            'nom' => 'CFA V2S',
            'siret' => '12345678900012',
            'theme_defaut' => 'institutionnel',
            'format_defaut' => 'pdf',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Le voisin n'a pas bougé : c'est tout l'objet de la fusion.
    expect($autre->fresh()->siret)->toBe('99999999900099');
});
