<?php

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
        $middleware->trustProxies(at: '*');

        // Les webhooks (callbacks des prestataires de signature eIDAS) sont
        // authentifiés par secret partagé, pas par jeton CSRF de session.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
