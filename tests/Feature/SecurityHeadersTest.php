<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pose les en-têtes de sécurité sur les réponses web', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('n\'envoie HSTS que sur une connexion sécurisée', function () {
    // En http (dev/test), pas de HSTS.
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    // En https, l'en-tête est présent.
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('n\'empêche pas l\'accès à l\'ERP (login toujours servi)', function () {
    // Garde-fou : les en-têtes ne cassent pas le panneau Filament.
    $this->get('/admin/login')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
