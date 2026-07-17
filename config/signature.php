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
    |  - « yousign »     : prestataire réel (API v3). Nécessite un abonnement et
    |                      une clé d'API. Tant que YOUSIGN_API_KEY est vide, le
    |                      driver se déclare inactif : le bouton « Envoyer en
    |                      signature » reste hors service plutôt que d'échouer.
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
    |
    | Yousign ne l'utilise PAS : il signe ses webhooks en HMAC-SHA256
    | (« yousign.webhook_secret » ci-dessous). Ce secret-ci reste pour les
    | prestataires à secret partagé simple.
    */
    'webhook_secret' => env('SIGNATURE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Yousign (API v3)
    |--------------------------------------------------------------------------
    |
    | Documentation : https://developers.yousign.com/ (la marque communique
    | désormais aussi sous « Youtrust » ; l'API reste yousign.app/v3).
    |
    | Mise en service :
    |  1. souscrire un abonnement Yousign et générer une clé d'API ;
    |  2. renseigner YOUSIGN_API_KEY (+ YOUSIGN_SANDBOX=true pour les essais) ;
    |  3. déclarer le webhook chez Yousign vers /webhooks/signature/yousign,
    |     et recopier son secret dans YOUSIGN_WEBHOOK_SECRET ;
    |  4. passer SIGNATURE_DRIVER=yousign.
    |
    | Sans clé, rien ne casse : le driver reste inactif (voir estActif()).
    |
    */
    'yousign' => [
        'api_key' => env('YOUSIGN_API_KEY'),

        // Bac à sable Yousign : parcours complet, sans valeur légale ni
        // consommation de crédits. À utiliser tant que l'abonnement n'est pas
        // en production.
        'sandbox' => (bool) env('YOUSIGN_SANDBOX', true),

        'base_url' => env('YOUSIGN_BASE_URL'),

        // Secret du webhook Yousign : sert à vérifier l'en-tête
        // « x-yousign-signature-256 » (HMAC-SHA256 du corps brut).
        'webhook_secret' => env('YOUSIGN_WEBHOOK_SECRET'),

        // Niveau de signature demandé aux signataires.
        //  - electronic_signature          : signature simple (défaut Yousign) ;
        //  - advanced_electronic_signature : signature avancée (identité vérifiée) ;
        //  - qualified_electronic_signature: signature qualifiée eIDAS.
        // ⚠️ Le niveau requis pour un contrat d'apprentissage est une question
        // juridique : à trancher avec Yousign et à vérifier sur source officielle
        // avant toute mise en production.
        'niveau' => env('YOUSIGN_NIVEAU', 'electronic_signature'),

        // Mode d'authentification du signataire (otp_sms exige un téléphone
        // valide pour chaque partie).
        'authentification' => env('YOUSIGN_AUTHENTIFICATION', 'no_otp'),
    ],

];
