<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP appliqués à toutes les réponses web.
 *
 * Volontairement SANS Content-Security-Policy stricte : Filament, Livewire et
 * Alpine s'appuient sur du script/style inline, qu'une CSP restrictive
 * casserait. La CSP est un chantier à part (à tester panneau par panneau) ;
 * ici on pose les protections sûres et à fort impact.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Empêche le navigateur de « deviner » un type MIME (anti-sniffing).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Anti-clickjacking : l'app ne peut pas être embarquée dans une iframe
        // d'un autre site. (SAMEORIGIN : les iframes internes restent permises.)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Équivalent moderne de X-Frame-Options (mieux respecté par les
        // navigateurs récents). Volontairement LIMITÉ à frame-ancestors : une
        // CSP complète (script-src/style-src) casserait Filament/Livewire/Alpine
        // et fera l'objet d'un chantier dédié, testé écran par écran.
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        // Ne fuite pas l'URL complète (souvent porteuse d'identifiants/tokens)
        // vers les sites tiers.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Réduit la surface d'API navigateur exposée par défaut.
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS : uniquement servi sur une connexion déjà sécurisée (sur http, le
        // navigateur l'ignorerait ; l'imposer casserait le dev local en http).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
