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
        // Sections de la réponse à agréger : offres publiées (jobs) + entreprises
        // susceptibles de recruter sans offre (recruiters). Schéma confirmé sur l'API réelle.
        'results_keys' => ['jobs', 'recruiters'],
        'timeout' => (int) env('LBA_TIMEOUT', 15),
        'default_radius' => (int) env('LBA_DEFAULT_RADIUS', 30),
        // Vérification du certificat SSL. Laisser à true en production. En dev
        // Windows, si cURL n'a pas de bundle CA (« cURL error 60 »), soit on
        // configure curl.cainfo dans php.ini (recommandé), soit LBA_VERIFY_SSL=false.
        'verify_ssl' => filter_var(env('LBA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        // Centre de recherche par défaut = localisation du CFA.
        'center' => [
            'lat' => (float) env('LBA_CENTER_LAT', 48.8566),
            'lon' => (float) env('LBA_CENTER_LON', 2.3522),
        ],
    ],

    /*
    | Géocodage d'une ville / code postal via l'API Adresse (BAN), publique et
    | gratuite (sans clé). Sert à centrer la prospection sur un lieu saisi.
    | Réutilise le même toggle SSL dev que La Bonne Alternance (cURL error 60).
    */
    'geocoder' => [
        'base_url' => env('GEOCODER_BASE_URL', 'https://api-adresse.data.gouv.fr'),
        'verify_ssl' => filter_var(env('LBA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | France Compétences — API SIRO (« Quel est mon OPCO »)
    |--------------------------------------------------------------------------
    | Détection de l'OPCO d'une entreprise à partir de son SIRET. Backend du
    | service public quel-est-mon-opco.francecompetences.fr ; la clé par défaut
    | est la clé publique embarquée dans ce site officiel.
    */
    'francecompetences' => [
        'siro_url' => env('FC_SIRO_URL', 'https://api.francecompetences.fr/siro/v1'),
        'siro_key' => env('FC_SIRO_KEY', 'e238f8a5-cc05-480e-b8f8-a5cc05180e10'),
    ],

];
