<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Formation;
use App\Models\User;
use App\Rules\TelephoneInternational;
use App\Support\Indicatifs;
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

it('accepte un numéro national à la création d\'un candidat, l\'indicatif du pays étant ajouté (en-app)', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    // Numéro national « 06… » avec France (pays par défaut du sélecteur) :
    // accepté, l'indicatif est ajouté automatiquement au numéro enregistré.
    Livewire::test(CreateCandidate::class)
        ->fillForm(['nom' => 'Test', 'prenom' => 'National', 'telephone' => '0612345678'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(\App\Models\Candidate::where('prenom', 'National')->value('telephone'))
        ->toBe('+33 612345678');

    // Une saisie réellement invalide (lettres) reste rejetée, même après combinaison.
    Livewire::test(CreateCandidate::class)
        ->fillForm(['nom' => 'Test', 'prenom' => 'Invalide', 'telephone' => 'abc'])
        ->call('create')
        ->assertHasFormErrors(['telephone']);

    // Un numéro déjà au format international passe aussi.
    Livewire::test(CreateCandidate::class)
        ->fillForm(['nom' => 'Test', 'prenom' => 'Avec Indicatif', 'telephone' => '+33612345678'])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('combine l\'indicatif choisi avec le numéro national (formulaire public)', function () {
    Formation::factory()->create();

    // Pays France + numéro national « 06… » → accepté (indicatif ajouté).
    $this->post(route('candidature.store'), [
        'nom' => 'Martin', 'prenom' => 'Léa', 'email' => 'lea@example.test',
        'indicatif_pays' => 'FR', 'telephone' => '0612345678',
    ])->assertSessionDoesntHaveErrors('telephone');

    // Numéro réellement invalide (lettres) → rejeté même après combinaison.
    $this->post(route('candidature.store'), [
        'nom' => 'Martin', 'prenom' => 'Léa', 'email' => 'lea@example.test',
        'indicatif_pays' => 'FR', 'telephone' => 'abc',
    ])->assertSessionHasErrors('telephone');
});

it('combine, détecte et applique correctement selon le pays ISO (helper)', function () {
    // Combinaison côté contrôleur (le pays est un code ISO).
    expect(Indicatifs::combiner('0612345678', 'FR'))->toBe('+33 612345678')
        ->and(Indicatifs::combiner('+32 470 12 34 56', 'FR'))->toBe('+32 470 12 34 56') // déjà international : conservé
        ->and(Indicatifs::combiner('', 'FR'))->toBe('');

    // Détection du pays d'un numéro stocké (présélection en édition) → code ISO.
    expect(Indicatifs::detecter('+32470123456'))->toBe('BE')
        ->and(Indicatifs::detecter('+33612345678'))->toBe('FR')
        ->and(Indicatifs::detecter(null))->toBe('FR');

    // Application côté formulaire (préfixe le champ avec l'indicatif du pays).
    expect(Indicatifs::appliquer('0612345678', 'FR'))->toBe('+33 612345678')
        ->and(Indicatifs::appliquer('+33 6 12', 'BE'))->toBe('+32 612'); // change de pays

    // Drapeau calculé depuis le code ISO (indicateurs régionaux Unicode).
    expect(Indicatifs::drapeau('FR'))->toBe('🇫🇷')
        ->and(Indicatifs::drapeau('BE'))->toBe('🇧🇪')
        ->and(count(Indicatifs::options()))->toBeGreaterThan(150);
});
