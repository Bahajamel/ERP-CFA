<?php

use App\Livret\LivretRsClient;
use App\Livret\LivretRsException;
use Illuminate\Support\Facades\Http;

it('écrit le ZIP renvoyé par le service dans un fichier temporaire', function () {
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response('PK-contenu-zip', 200)]);

    $chemin = (new LivretRsClient)->genererLivrables(['cfa' => ['nom' => 'X']]);

    expect(is_file($chemin))->toBeTrue()
        ->and(file_get_contents($chemin))->toBe('PK-contenu-zip');

    @unlink($chemin);
});

it('lève une exception si le service répond en erreur', function () {
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn () => (new LivretRsClient)->genererLivrables([]))
        ->toThrow(LivretRsException::class);
});

it('est désactivé et refuse la génération sans URL configurée', function () {
    config(['services.livretrs.url' => null]);

    expect((new LivretRsClient)->estConfigure())->toBeFalse()
        ->and(fn () => (new LivretRsClient)->genererLivrables([]))
        ->toThrow(LivretRsException::class);
});
