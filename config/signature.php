<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prestataire de signature électronique
    |--------------------------------------------------------------------------
    |
    | Pilote la signature électronique multi-parties des contrats (EPIC-08).
    |
    |  - « none »        : signature électronique désactivée (signature manuelle
    |                      uniquement — on marque « signé » à la main).
    |  - « simulation »  : parcours complet SANS prestataire réel. L'envoi et la
    |                      signature sont simulés localement — pour la démo et le
    |                      développement. Aucune valeur probante eIDAS.
    |  - (à venir)       : « yousign », « docaposte », « universign »… un
    |                      prestataire eIDAS réel, branché sur la même interface
    |                      App\Signature\Contracts\SignatureProvider.
    |
    | En production, viser un prestataire qualifié eIDAS. La simulation ne doit
    | JAMAIS servir à produire un contrat opposable.
    |
    */

    'driver' => env('SIGNATURE_DRIVER', 'simulation'),

    /*
    | Délai d'expiration d'une demande de signature (en jours) — indicatif,
    | exploité par les prestataires réels.
    */
    'expiration_jours' => (int) env('SIGNATURE_EXPIRATION_JOURS', 14),

    /*
    | Secret partagé pour authentifier les callbacks (webhooks) des prestataires
    | réels. Laisser vide en simulation.
    */
    'webhook_secret' => env('SIGNATURE_WEBHOOK_SECRET'),

];
