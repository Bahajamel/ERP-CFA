<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disque privé des pièces sensibles
    |--------------------------------------------------------------------------
    |
    | Les pièces contenant des données personnelles / NIR (pièce d'identité,
    | carte vitale, CERFA, conventions…) ne doivent JAMAIS être servies par un
    | disque web-exposé. Elles vivent sur ce disque privé (par défaut « local »,
    | racine storage/app/private, hors du dossier public) et ne sont accessibles
    | que via la route sécurisée `documents.securise` (authentifiée + signée).
    |
    | En production SaaS, pointer ce disque vers un bucket objet privé (S3) :
    | MEDIA_PRIVATE_DISK=s3 (bucket sans accès public).
    |
    */

    'disque_prive' => env('MEDIA_PRIVATE_DISK', 'local'),

    /*
    | Durée de validité d'un lien signé vers une pièce sensible (minutes).
    | Court par principe : le lien expire vite, il n'est ni devinable ni
    | partageable durablement.
    */

    'lien_expiration_minutes' => (int) env('MEDIA_LIEN_EXPIRATION', 15),

];
