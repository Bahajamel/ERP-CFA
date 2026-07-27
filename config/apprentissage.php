<?php

/*
|--------------------------------------------------------------------------
| Paramètres réglementaires du contrat d'apprentissage
|--------------------------------------------------------------------------
| Sources officielles (vérifiées le 08/07/2026) :
| - SMIC : info.gouv.fr — revalorisations du 01/01/2026 (+1,18 %) et du
|   01/06/2026 (+2,41 %, mécanisme automatique d'inflation).
| - La grille de rémunération (% du SMIC) vit dans
|   App\Support\RemunerationApprenti (art. D6222-26 du Code du travail).
*/

return [

    /*
    | SMIC mensuel brut (base 35 h), par date d'entrée en vigueur.
    | La valeur la plus récente antérieure ou égale à la date visée
    | s'applique. À compléter à chaque revalorisation.
    */
    'smic_mensuel_brut' => [
        '2024-11-01' => 1801.80,
        '2026-01-01' => 1823.03,
        '2026-06-01' => 1867.02,
    ],

    /*
    | Montants repères des frais annexes finançables par l'OPCO (plafonds
    | réglementaires). Affichés comme repères dans l'onglet Contrat ; jamais
    | codés en dur dans la logique métier. À ajuster selon la réglementation.
    */
    'frais_annexes' => [
        'hebergement_par_nuit' => 6,
        'restauration_par_repas' => 3,
        'premier_equipement' => 500,
    ],

];
