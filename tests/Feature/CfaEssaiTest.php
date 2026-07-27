<?php

use App\Enums\DemoRequestStatut;
use App\Filament\Editeur\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Filament\Editeur\Resources\Organisations\Pages\ListOrganisations;
use App\Mail\BienvenueEssaiCfa;
use App\Models\DemoRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Provisioning\ProvisionnerCfaEssai;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ── Service de provisioning ───────────────────────────────────────────────────

it('ouvre un CFA en essai avec son compte administrateur', function () {
    $r = app(ProvisionnerCfaEssai::class)->creer('CFA des Métiers', 'admin@cfa-metiers.test', 'Marie Dupont', 30);

    $organisation = $r['organisation'];
    $admin = $r['admin'];

    expect($organisation->nom)->toBe('CFA des Métiers')
        ->and($organisation->slug)->toBe('cfa-des-metiers')
        ->and($organisation->actif)->toBeTrue()
        ->and($organisation->date_fin_essai)->not->toBeNull()
        ->and($organisation->joursEssaiRestants())->toBe(30)
        ->and($admin->email)->toBe('admin@cfa-metiers.test')
        ->and($admin->name)->toBe('Marie Dupont')
        ->and($admin->hasRole('Administrateur'))->toBeTrue()
        // Rattaché au CFA et capable d'y accéder.
        ->and($admin->organisations()->whereKey($organisation->id)->exists())->toBeTrue()
        ->and($admin->canAccessTenant($organisation))->toBeTrue()
        // Mot de passe temporaire renvoyé une fois, pour transmission.
        ->and($r['mot_de_passe'])->toBeString()
        ->and($r['compte_existant'])->toBeFalse();
});

it('génère un slug unique quand le nom est déjà pris', function () {
    Organisation::factory()->create(['slug' => 'cfa-des-metiers']);

    $r = app(ProvisionnerCfaEssai::class)->creer('CFA des Métiers', 'x@example.test');

    expect($r['organisation']->slug)->toBe('cfa-des-metiers-2');
});

it('rattache un compte existant sans réinitialiser son mot de passe', function () {
    $existant = User::factory()->create(['email' => 'deja@example.test', 'is_active' => false]);
    $motDePasseAvant = $existant->password;

    $r = app(ProvisionnerCfaEssai::class)->creer('Nouveau CFA', 'deja@example.test');

    expect($r['compte_existant'])->toBeTrue()
        ->and($r['mot_de_passe'])->toBeNull()
        ->and($r['admin']->is($existant))->toBeTrue()
        // Mot de passe intact, compte réactivé, rattaché au nouveau CFA.
        ->and($r['admin']->fresh()->password)->toBe($motDePasseAvant)
        ->and($r['admin']->fresh()->is_active)->toBeTrue()
        ->and($r['admin']->organisations()->whereKey($r['organisation']->id)->exists())->toBeTrue();
});

it('ne crée jamais un administrateur de CFA avec l\'accès éditeur', function () {
    $r = app(ProvisionnerCfaEssai::class)->creer('CFA X', 'admin@cfa-x.test');

    // Garde-fou du modèle SaaS : un admin CFA n'accède pas au panneau éditeur.
    expect($r['admin']->can('access_editeur'))->toBeFalse();
});

// ── Suspension à échéance ─────────────────────────────────────────────────────

it('suspend les CFA dont l\'essai est arrivé à échéance', function () {
    $expire = Organisation::factory()->create(['actif' => true, 'date_fin_essai' => now()->subDay()]);
    $enCours = Organisation::factory()->create(['actif' => true, 'date_fin_essai' => now()->addDays(10)]);
    $sansEssai = Organisation::factory()->create(['actif' => true, 'date_fin_essai' => null]);

    $this->artisan('essai:suspendre-expires')->assertSuccessful();

    expect($expire->fresh()->actif)->toBeFalse()
        // Un essai en cours ou un CFA hors essai ne sont pas touchés.
        ->and($enCours->fresh()->actif)->toBeTrue()
        ->and($sansEssai->fresh()->actif)->toBeTrue();
});

it('bloque l\'accès d\'un membre après suspension de l\'essai', function () {
    $r = app(ProvisionnerCfaEssai::class)->creer('CFA Expirable', 'admin@expirable.test');
    $organisation = $r['organisation'];
    $admin = $r['admin'];

    expect($admin->canAccessTenant($organisation))->toBeTrue();

    $organisation->update(['date_fin_essai' => now()->subDay()]);
    $this->artisan('essai:suspendre-expires');

    // Accès effectivement coupé (mécanisme actif=false).
    expect($admin->fresh()->canAccessTenant($organisation->fresh()))->toBeFalse();
});

// ── Conversion d'une demande de démo ──────────────────────────────────────────

it('convertit une demande de démo en essai gratuit depuis l\'espace éditeur', function () {
    Filament::setCurrentPanel(Filament::getPanel('editeur'));
    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->syncRoles('Éditeur');
    $this->actingAs($editeur);

    $demande = DemoRequest::create([
        'first_name' => 'Léa',
        'last_name' => 'Durand',
        'organization_name' => 'CFA des Alpes',
        'email' => 'lea@cfa-alpes.test',
        'status' => DemoRequestStatut::Nouveau,
        'consent_at' => now(),
    ]);

    Livewire::test(ListDemoRequests::class)
        ->callTableAction('convertirEnEssai', $demande, data: [
            'nom_cfa' => 'CFA des Alpes',
            'email_admin' => 'lea@cfa-alpes.test',
            'nom_admin' => 'Léa Durand',
            'jours_essai' => 30,
        ])
        ->assertNotified();

    // La demande est convertie et le CFA existe avec son admin.
    expect($demande->fresh()->status)->toBe(DemoRequestStatut::Converti);

    $organisation = Organisation::where('slug', 'cfa-des-alpes')->first();
    expect($organisation)->not->toBeNull()
        ->and($organisation->date_fin_essai)->not->toBeNull();

    $admin = User::where('email', 'lea@cfa-alpes.test')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('Administrateur'))->toBeTrue()
        ->and($admin->organisations()->whereKey($organisation->id)->exists())->toBeTrue();
});

// ── E-mail de bienvenue au prospect ───────────────────────────────────────────

/** Prépare un éditeur connecté sur son panneau + une demande de démo à convertir. */
function convertir(string $email, string $nomCfa, array $data): void
{
    Filament::setCurrentPanel(Filament::getPanel('editeur'));
    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->syncRoles('Éditeur');
    test()->actingAs($editeur);

    $demande = DemoRequest::create([
        'first_name' => 'Léa',
        'last_name' => 'Durand',
        'organization_name' => $nomCfa,
        'email' => $email,
        'status' => DemoRequestStatut::Nouveau,
        'consent_at' => now(),
    ]);

    Livewire::test(ListDemoRequests::class)
        ->callTableAction('convertirEnEssai', $demande, data: array_merge([
            'nom_cfa' => $nomCfa,
            'email_admin' => $email,
            'nom_admin' => 'Léa Durand',
            'jours_essai' => 30,
        ], $data));
}

it('envoie les accès par e-mail au prospect lors de la conversion', function () {
    Mail::fake();

    convertir('lea@cfa-alpes.test', 'CFA des Alpes', ['envoyer_email' => true]);

    Mail::assertSent(BienvenueEssaiCfa::class, function (BienvenueEssaiCfa $mail): bool {
        return $mail->hasTo('lea@cfa-alpes.test')
            && $mail->nomCfa === 'CFA des Alpes'
            && $mail->motDePasse !== null;           // compte neuf → mot de passe transmis
    });
});

it('n\'envoie pas d\'e-mail quand l\'éditeur désactive l\'option', function () {
    Mail::fake();

    convertir('tom@cfa-test.test', 'CFA Test', ['envoyer_email' => false]);

    Mail::assertNothingSent();
});

it('transmet un e-mail sans mot de passe pour un compte déjà existant', function () {
    Mail::fake();
    User::factory()->create(['email' => 'connu@cfa.test']);

    convertir('connu@cfa.test', 'CFA Connu', ['envoyer_email' => true]);

    Mail::assertSent(BienvenueEssaiCfa::class, fn (BienvenueEssaiCfa $mail): bool => $mail->motDePasse === null);
});

// ── Conversion en client payant ───────────────────────────────────────────────

it('convertit un CFA en client payant en levant l\'échéance d\'essai', function () {
    Filament::setCurrentPanel(Filament::getPanel('editeur'));
    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->syncRoles('Éditeur');
    $this->actingAs($editeur);

    $cfa = Organisation::factory()->create(['actif' => true, 'date_fin_essai' => now()->addDays(5)]);

    Livewire::test(ListOrganisations::class)
        ->callTableAction('convertirEnClient', $cfa);

    expect($cfa->fresh()->date_fin_essai)->toBeNull()
        ->and($cfa->fresh()->estEnEssai())->toBeFalse();

    // N'est plus suspendu par la commande d'échéance (plus d'essai en cours).
    $this->artisan('essai:suspendre-expires')->assertSuccessful();
    expect($cfa->fresh()->actif)->toBeTrue();
});

// ── Bandeau d'échéance côté CFA ────────────────────────────────────────────────

it('affiche le bandeau d\'essai pour un CFA en essai', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $cfa = Organisation::factory()->create(['date_fin_essai' => now()->addDays(5)]);
    Filament::setTenant($cfa);

    $html = view('filament.essai-banner')->render();

    expect($html)->toContain('essai gratuit')
        ->and($html)->toContain('🎁');
});

it('n\'affiche aucun bandeau pour un CFA hors essai', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $cfa = Organisation::factory()->create(['date_fin_essai' => null]);
    Filament::setTenant($cfa);

    expect(trim(view('filament.essai-banner')->render()))->toBe('');
});
