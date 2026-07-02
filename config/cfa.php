<?php

/*
|--------------------------------------------------------------------------
| Identité du CFA
|--------------------------------------------------------------------------
|
| Informations propres au centre de formation, injectées dans les livrables
| générés par LivretRS (en-têtes, mentions légales, référents). Configurables
| par variables d'environnement — aucune donnée sensible d'apprenti ici.
|
*/

return [
    'nom' => env('CFA_NOM', 'CFA'),
    'raison_sociale' => env('CFA_RAISON_SOCIALE'),
    'siret' => env('CFA_SIRET'),
    'siren' => env('CFA_SIREN'),
    'naf' => env('CFA_NAF'),
    'nda' => env('CFA_NDA'),               // numéro de déclaration d'activité
    'numero_uai' => env('CFA_UAI'),
    'adresse' => env('CFA_ADRESSE'),
    'code_postal' => env('CFA_CODE_POSTAL'),
    'ville' => env('CFA_VILLE'),
    'telephone' => env('CFA_TELEPHONE'),
    'email' => env('CFA_EMAIL'),
    'website' => env('CFA_WEBSITE'),

    // Référents (obligatoires pour plusieurs livrables Qualiopi / L6231-2).
    'representant_legal' => [
        'nom' => env('CFA_REPRESENTANT_NOM'),
        'prenom' => env('CFA_REPRESENTANT_PRENOM'),
        'fonction' => env('CFA_REPRESENTANT_FONCTION', 'Directeur'),
    ],
    'referent_pedagogique' => [
        'nom' => env('CFA_REF_PEDAGOGIQUE_NOM'),
        'prenom' => env('CFA_REF_PEDAGOGIQUE_PRENOM'),
        'fonction' => env('CFA_REF_PEDAGOGIQUE_FONCTION', 'Référent pédagogique'),
    ],
    'referent_handicap' => [
        'nom' => env('CFA_REF_HANDICAP_NOM'),
        'prenom' => env('CFA_REF_HANDICAP_PRENOM'),
        'fonction' => env('CFA_REF_HANDICAP_FONCTION', 'Référent handicap'),
    ],
];
