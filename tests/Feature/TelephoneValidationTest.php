<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Formation;
use App\Models\User;
use App\Rules\TelephoneInternational;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** true si la valeur passe la règle téléphone international. */
function telValide(?string $valeur): bool
{
    $ok = true;
    (new TelephoneInternational)->validate('telephone', $valeur, function () use (&$ok): void {
        $ok = false;
    });

    return $ok;
}

it('accepte les numéros au format international (avec indicatif)', function () {
    expect(telValide('+33612345678'))->toBeTrue()
        ->and(telValide('+33 6 12 34 56 78'))->toBeTrue()
        ->and(telValide('+33.6.12.34.56.78'))->toBeTrue()
        ->and(telValide('+32 470 12 34 56'))->toBeTrue()
        ->and(telValide(null))->toBeTrue()   // vide : l'obligation est gérée ailleurs
        ->and(telValide(''))->toBeTrue();
});

it('rejette les numéros sans indicatif pays ou mal formés', function () {
    expect(telValide('0612345678'))->toBeFalse()      // FR sans indicatif
        ->and(telValide('06 12 34 56 78'))->toBeFalse()
        ->and(telValide('12345'))->toBeFalse()          // trop court, pas de +
        ->and(telValide('+0612345678'))->toBeFalse()    // indicatif ne peut commencer par 0
        ->and(telValide('téléphone'))->toBeFalse()
        ->and(telValide('06 12 <img src=x>'))->toBeFalse();
});

it('bloque un téléphone sans indicatif à la création d\'un candidat (en-app)', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    Livewire::test(CreateCandidate::class)
        ->fillForm(['nom' => 'Test', 'prenom' => 'Sans Indicatif', 'telephone' => '0612345678'])
        ->call('create')
        ->assertHasFormErrors(['telephone']);

    Livewire::test(CreateCandidate::class)
        ->fillForm(['nom' => 'Test', 'prenom' => 'Avec Indicatif', 'telephone' => '+33612345678'])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('bloque un téléphone sans indicatif sur le formulaire public de candidature', function () {
    Formation::factory()->create();

    $this->post(route('candidature.store'), [
        'nom' => 'Martin',
        'prenom' => 'Léa',
        'email' => 'lea@example.test',
        'telephone' => '0612345678',
    ])->assertSessionHasErrors('telephone');
});
