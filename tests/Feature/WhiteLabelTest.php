<?php

use App\Filament\Pages\Tenancy\ProfilCfa;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un CFA actif + un membre (Direction) connecté, prêt à ouvrir son espace. */
function cfaAvecMembre(array $attrs = []): array
{
    $cfa = Organisation::factory()->create(array_merge(['actif' => true], $attrs));
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Direction');
    $cfa->users()->attach($user);

    return [$cfa, $user];
}

it('stocke la couleur principale du CFA', function () {
    $organisation = Organisation::factory()->create(['couleur_primaire' => '#10b981']);

    expect($organisation->fresh()->couleur_primaire)->toBe('#10b981');
});

it('génère une palette complète à partir de la couleur du CFA', function () {
    $palette = Color::hex('#10b981');

    expect($palette)->toHaveKeys([50, 100, 500, 600, 900, 950]);
});

it('applique réellement la couleur du CFA dans le rendu de la page (HTTP)', function () {
    $this->seed(RolePermissionSeeder::class);
    [$cfa, $user] = cfaAvecMembre(['couleur_primaire' => '#10b981']);

    $html = $this->actingAs($user)->get('/admin/'.$cfa->slug)->assertOk()->getContent();

    // La couleur est injectée en fin de <head> : sa définition de --primary-600
    // (émeraude) doit être présente et l'emporter par cascade sur l'indigo par défaut.
    expect($html)->toContain('--primary-600:'.Color::hex('#10b981')[600])
        // La barre latérale et le bouton « + » suivent aussi la couleur du CFA.
        ->and($html)->toContain('.fi-sidebar{background-color:var(--primary-950)')
        ->and($html)->toContain('.cfa-create-btn{background-image:linear-gradient(135deg,var(--primary-500)');
});

it('garde le thème par défaut quand le CFA n\'a pas de couleur', function () {
    $this->seed(RolePermissionSeeder::class);
    [$cfa, $user] = cfaAvecMembre(['couleur_primaire' => null]);

    $html = $this->actingAs($user)->get('/admin/'.$cfa->slug)->assertOk()->getContent();

    // Aucune surcharge injectée → pas de bloc :root personnalisé.
    expect($html)->not->toContain(':root{--primary-50:');
});

it('propose le choix de la couleur et du logo dans la fiche du CFA', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    Livewire::test(ProfilCfa::class)
        ->assertOk()
        ->assertSee('Identité visuelle')
        ->assertSee('Couleur principale');
});
