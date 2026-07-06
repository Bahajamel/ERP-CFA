<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Service de génération de livrables LivretRS (moteur core/ exposé en
    | HTTP, stateless / privacy-safe). Vide = génération automatique
    | désactivée (l'import manuel du pack ZIP reste disponible).
    */
    'livretrs' => [
        'url' => env('LIVRETRS_URL'),
        'timeout' => (int) env('LIVRETRS_TIMEOUT', 180),
    ],

    /*
    | API La Bonne Alternance (mission-apprentissage / api.apprentissage.beta.gouv.fr).
    | Recherche des entreprises qui recrutent en alternance pour un métier / une
    | formation (par code RNCP) autour d'un point géographique.
    | Clé (Bearer) obtenue sur l'espace développeurs. Vide = prospection désactivée.
    | ⚠️ Usage gratuit réservé au non-lucratif (revente des données interdite).
    | `results_key` et `search_path` sont paramétrables car le schéma de l'API évolue.
    */
    'labonnealternance' => [
        'api_key' => env('LBA_API_KEY'),
        'base_url' => env('LBA_BASE_URL', 'https://api.apprentissage.beta.gouv.fr'),
        'search_path' => env('LBA_SEARCH_PATH', '/api/job/v1/search'),
        'results_key' => env('LBA_RESULTS_KEY', 'recruiters'),
        'timeout' => (int) env('LBA_TIMEOUT', 15),
        'default_radius' => (int) env('LBA_DEFAULT_RADIUS', 30),
        // Centre de recherche par défaut = localisation du CFA.
        'center' => [
            'lat' => (float) env('LBA_CENTER_LAT', 48.8566),
            'lon' => (float) env('LBA_CENTER_LON', 2.3522),
        ],
    ],

];
