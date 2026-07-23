<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Pages publiques ───────────────────────────────────────────────────────────

it('sert la page d\'accueil de la vitrine sur la racine', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Meridian CFA')
        ->assertSee('Pilotez votre CFA')
        // Les grandes sections attendues par la spec sont présentes.
        ->assertSee('Fonctionnalités')
        ->assertSee('Pour qui ?')
        ->assertSee('Sécurité')
        ->assertSee('Questions fréquentes');
});

it('affiche les pages légales', function () {
    $this->get(route('vitrine.mentions'))->assertOk()->assertSee('Mentions légales');
    $this->get(route('vitrine.confidentialite'))->assertOk()->assertSee('Politique de confidentialité');
});

it('expose robots.txt et le sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        // Les espaces authentifiés restent hors des crawlers.
        ->assertSee('Disallow: /admin');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<urlset', false);
});

// ── Lien vers l'ERP existant (aucune auth recréée) ────────────────────────────

it('pointe « Se connecter » vers la connexion Filament existante', function () {
    $this->get('/')->assertSee(route('filament.admin.auth.login'));
});

it('affiche « Mon espace » pour un utilisateur déjà connecté', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Mon espace')
        ->assertDontSee('Se connecter');
});

// ── Non-régression : l'ERP et les formulaires publics restent intacts ─────────

it('laisse l\'ERP protégé derrière l\'authentification', function () {
    // Non authentifié → redirection vers la connexion, jamais la vitrine.
    $this->get('/admin')->assertRedirect();
});

it('ne casse pas les formulaires publics existants', function () {
    $this->get(route('entreprise.create'))->assertOk();
    $this->get(route('candidature.create'))->assertOk();
});
