<?php

use App\Cerfa\CerfaApprentissage;
use App\Models\SignatureRequest;
use App\Signature\Providers\YousignSignatureProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Connecteur Yousign (API v3) — éprouvé sans jamais appeler Yousign.
 *
 * Aucun abonnement n'est souscrit à ce jour : le driver reste dormant en
 * production (estActif() = false sans clé). Ces tests vérifient qu'il fera la
 * bonne chose le jour où la clé arrivera.
 */
beforeEach(function () {
    config()->set('signature.yousign.api_key', 'test-cle-api');
    config()->set('signature.yousign.sandbox', true);
    config()->set('signature.yousign.niveau', 'electronic_signature');
    config()->set('signature.yousign.authentification', 'no_otp');

    // Le CERFA réel exige un contrat complet : on ne teste pas le PDF ici.
    $this->mock(CerfaApprentissage::class)
        ->shouldReceive('pour')->andReturn('%PDF-1.4 faux contrat');
});

function providerYousign(): YousignSignatureProvider
{
    return app(YousignSignatureProvider::class);
}

/** Enveloppe locale rattachée à un contrat, avec deux parties. */
function enveloppeYousign(): SignatureRequest
{
    $contract = \App\Models\Contract::factory()->create();

    return SignatureRequest::create([
        'contract_id' => $contract->id,
        'provider' => 'yousign',
        'statut' => \App\Enums\SignatureRequestStatut::Brouillon->value,
        'signataires' => [
            ['role' => 'apprenti', 'libelle' => 'Apprenti', 'nom' => 'Marie Dupont', 'email' => 'marie@exemple.fr', 'ordre' => 1, 'signe_at' => null],
            ['role' => 'cfa', 'libelle' => 'CFA', 'nom' => 'CFA V2S', 'email' => 'direction@cfa-v2s.fr', 'ordre' => 2, 'signe_at' => null],
        ],
    ]);
}

it('se déclare inactif tant qu’aucune clé d’API n’est configurée', function () {
    config()->set('signature.yousign.api_key', null);

    // Le point clé : sans abonnement, le bouton « Envoyer en signature »
    // disparaît au lieu d'échouer devant l'utilisateur.
    expect(providerYousign()->estActif())->toBeFalse();

    config()->set('signature.yousign.api_key', 'test-cle-api');

    expect(providerYousign()->estActif())->toBeTrue();
});

it('déroule les quatre étapes de l’envoi, dans l’ordre', function () {
    Http::fake([
        '*/signature_requests' => Http::response(['id' => 'sr-123'], 201),
        '*/signature_requests/sr-123/documents' => Http::response(['id' => 'doc-456'], 201),
        '*/signature_requests/sr-123/signers' => Http::response(['id' => 'signer-1'], 201),
        '*/signature_requests/sr-123/activate' => Http::response(['status' => 'ongoing'], 200),
    ]);

    $externalId = providerYousign()->envoyer(enveloppeYousign());

    // L'identifiant d'enveloppe Yousign est ce que l'ERP stocke en external_id :
    // c'est lui qui permettra de rattacher les webhooks de retour.
    expect($externalId)->toBe('sr-123');

    $appels = collect();
    Http::assertSentInOrder([
        function (Request $r) use ($appels) {
            $appels->push($r);

            return str_ends_with($r->url(), '/signature_requests')
                && $r['delivery_mode'] === 'email';
        },
        fn (Request $r): bool => str_contains($r->url(), '/documents'),
        fn (Request $r): bool => str_contains($r->url(), '/signers'),
        fn (Request $r): bool => str_contains($r->url(), '/signers'), // une requête par partie
        // L'activation vient EN DERNIER : c'est elle qui déclenche les mails.
        fn (Request $r): bool => str_contains($r->url(), '/activate'),
    ]);
});

it('vise le bac à sable tant que la production n’est pas activée', function () {
    Http::fake(['*' => Http::response(['id' => 'sr-1'], 201)]);

    providerYousign()->envoyer(enveloppeYousign());

    Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://api-sandbox.yousign.app/v3'));

    config()->set('signature.yousign.sandbox', false);
    Http::fake(['*' => Http::response(['id' => 'sr-2'], 201)]);

    providerYousign()->envoyer(enveloppeYousign());

    Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://api.yousign.app/v3'));
});

it('authentifie chaque appel avec la clé d’API', function () {
    Http::fake(['*' => Http::response(['id' => 'sr-1'], 201)]);

    providerYousign()->envoyer(enveloppeYousign());

    Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer test-cle-api'));
});

it('scinde le nom d’une personne morale sans envoyer de champ vide', function () {
    Http::fake(['*' => Http::response(['id' => 'sr-1'], 201)]);

    providerYousign()->envoyer(enveloppeYousign());

    // « Marie Dupont » → prénom/nom ; « CFA V2S » → Yousign exige les deux champs,
    // on ne doit jamais lui envoyer une chaîne vide (l'API refuserait).
    Http::assertSent(function (Request $r): bool {
        if (! str_contains($r->url(), '/signers')) {
            return false;
        }

        return filled($r['info']['first_name']) && filled($r['info']['last_name']);
    });
});

it('signale clairement une réponse inattendue de Yousign', function () {
    Http::fake(['*/signature_requests' => Http::response(['pas_d_id' => true], 201)]);

    providerYousign()->envoyer(enveloppeYousign());
})->throws(RuntimeException::class, 'identifiant absent');

it('n’échoue pas si l’annulation chez Yousign est injoignable', function () {
    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    $enveloppe = enveloppeYousign();
    $enveloppe->update(['external_id' => 'sr-123']);

    // Le contrat reste maître côté ERP : un prestataire injoignable ne doit pas
    // empêcher d'annuler une demande de signature.
    providerYousign()->annuler($enveloppe);
})->throwsNoExceptions();
