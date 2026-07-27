<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiance aux proxies : derrière un tunnel de partage (cloudflared /
        // localtunnel) ou un reverse-proxy de staging, Laravel détecte le bon
        // schéma (https) et le bon hôte via les en-têtes X-Forwarded-* — sans
        // quoi les assets partent en http (page cassée) et le CSRF échoue (419).
        //
        // Par défaut « * » (pratique en local/tunnel) ; EN PROD, restreindre à
        // l'IP du reverse-proxy via TRUSTED_PROXIES (ex. « 10.0.0.1 » ou une
        // liste séparée par des virgules) — sinon un client peut usurper son IP
        // et son schéma via des en-têtes X-Forwarded-* falsifiés.
        $proxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));

        // En-têtes de sécurité HTTP sur TOUTES les réponses (anti-sniffing,
        // anti-clickjacking, HSTS en HTTPS…). En global plutôt que dans le
        // groupe « web » : les panneaux Filament ont leur propre pile de
        // middleware et n'héritent pas des ajouts au groupe web.
        $middleware->append(SecurityHeaders::class);

        // Les webhooks (callbacks des prestataires de signature eIDAS) sont
        // authentifiés par secret partagé, pas par jeton CSRF de session.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Un invité qui tente d'accéder à une ressource protégée (ex. pièce
        // sensible via un vieux lien signé) est renvoyé vers le login Filament :
        // il n'existe pas de route nommée « login » générique dans l'application.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
