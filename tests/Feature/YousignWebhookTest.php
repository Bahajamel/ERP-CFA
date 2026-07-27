<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\SignatureRequestStatut;
use App\Models\Contract;
use App\Models\SignatureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Webhook Yousign : c'est lui qui fait passer un contrat à « Signé ».
 *
 * Autrement dit : quiconque sait forger cet appel signe des contrats à la place
 * des parties. D'où l'insistance sur la vérification HMAC ci-dessous.
 */
const SECRET_YOUSIGN = 'secret-webhook-de-test';

beforeEach(function () {
    config()->set('signature.yousign.webhook_secret', SECRET_YOUSIGN);
});

function enveloppeEnvoyee(): SignatureRequest
{
    return SignatureRequest::create([
        'contract_id' => Contract::factory()->create()->id,
        'provider' => 'yousign',
        'external_id' => 'sr-yousign-123',
        'statut' => SignatureRequestStatut::Envoyee->value,
        'signataires' => [
            ['role' => 'apprenti', 'libelle' => 'Apprenti', 'nom' => 'Marie Dupont', 'email' => 'marie@exemple.fr', 'ordre' => 1, 'signe_at' => null],
            ['role' => 'cfa', 'libelle' => 'CFA', 'nom' => 'CFA V2S', 'email' => 'direction@cfa-v2s.fr', 'ordre' => 2, 'signe_at' => null],
        ],
        'sent_at' => now(),
    ]);
}

/** Appelle le webhook en signant le corps comme le ferait Yousign. */
function appelYousign(array $payload, ?string $secret = SECRET_YOUSIGN)
{
    $corps = json_encode($payload);

    return test()->call(
        'POST',
        '/webhooks/signature/yousign',
        server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_YOUSIGN_SIGNATURE_256' => $secret === null
                ? null
                : 'sha256='.hash_hmac('sha256', $corps, $secret),
        ]),
        content: $corps,
    );
}

it('enregistre la signature d’une partie', function () {
    $enveloppe = enveloppeEnvoyee();

    appelYousign([
        'event_name' => 'signer.done',
        'data' => [
            'signature_request' => ['id' => 'sr-yousign-123'],
            'signer' => ['info' => ['email' => 'marie@exemple.fr']],
        ],
    ])->assertOk();

    $signataires = collect($enveloppe->fresh()->signataires);

    expect($signataires->firstWhere('email', 'marie@exemple.fr')['signe_at'])->not->toBeNull()
        // L'autre partie n'a pas signé : l'enveloppe n'est pas close.
        ->and($signataires->firstWhere('email', 'direction@cfa-v2s.fr')['signe_at'])->toBeNull();
});

it('refuse un appel dont la signature HMAC est fausse', function () {
    enveloppeEnvoyee();

    appelYousign([
        'event_name' => 'signer.done',
        'data' => [
            'signature_request' => ['id' => 'sr-yousign-123'],
            'signer' => ['info' => ['email' => 'marie@exemple.fr']],
        ],
    ], secret: 'mauvais-secret')->assertUnauthorized();
});

it('refuse un appel sans signature HMAC', function () {
    enveloppeEnvoyee();

    appelYousign([
        'event_name' => 'signer.done',
        'data' => ['signature_request' => ['id' => 'sr-yousign-123']],
    ], secret: null)->assertUnauthorized();
});

it('refuse tout si aucun secret n’est configuré', function () {
    // Sans secret, impossible d'authentifier l'appelant : on ferme, plutôt que
    // de laisser n'importe qui faire signer un contrat.
    config()->set('signature.yousign.webhook_secret', null);
    enveloppeEnvoyee();

    appelYousign([
        'event_name' => 'signer.done',
        'data' => ['signature_request' => ['id' => 'sr-yousign-123']],
    ])->assertUnauthorized();
});

it('ignore poliment les événements qui ne sont pas des signatures', function () {
    $enveloppe = enveloppeEnvoyee();

    // Activation, rappels… : acquittés sans rien changer.
    appelYousign([
        'event_name' => 'signature_request.activated',
        'data' => ['signature_request' => ['id' => 'sr-yousign-123']],
    ])->assertOk();

    expect(collect($enveloppe->fresh()->signataires)->whereNotNull('signe_at'))->toBeEmpty();
});

it('répond 404 sur une enveloppe inconnue', function () {
    appelYousign([
        'event_name' => 'signer.done',
        'data' => [
            'signature_request' => ['id' => 'enveloppe-fantome'],
            'signer' => ['info' => ['email' => 'marie@exemple.fr']],
        ],
    ])->assertNotFound();
});

it('clôt le contrat quand toutes les parties ont signé', function () {
    $enveloppe = enveloppeEnvoyee();

    foreach (['marie@exemple.fr', 'direction@cfa-v2s.fr'] as $email) {
        appelYousign([
            'event_name' => 'signer.done',
            'data' => [
                'signature_request' => ['id' => 'sr-yousign-123'],
                'signer' => ['info' => ['email' => $email]],
            ],
        ])->assertOk();
    }

    expect($enveloppe->fresh()->statut)->toBe(SignatureRequestStatut::Signee)
        ->and($enveloppe->fresh()->contract->statut_signature)
        ->toBe(ContractSignatureStatut::Signe);
});
