<?php

/**
 * Nomenclatures du CERFA 10103*14 (contrat d'apprentissage) réutilisées par les
 * formulaires du dossier ET, à terme, par la génération du document.
 *
 * Les clés sont des valeurs stables stockées en base ; les libellés sont
 * affichés à l'utilisateur. Alignées sur la notice officielle du CERFA.
 */
return [

    'sexe' => [
        'M' => 'Homme',
        'F' => 'Femme',
    ],

    // Nationalité de l'apprenti (rubrique CERFA : 1 Française / 2 UE / 3 hors UE).
    'nationalite' => [
        'francaise' => 'Française',
        'ue' => 'Union Européenne (hors France)',
        'hors_ue' => 'Étranger hors Union Européenne',
    ],

    'regime_social' => [
        'general' => 'Régime général (URSSAF)',
        'msa' => 'MSA (régime agricole)',
    ],

    // Situation de l'apprenti avant ce contrat (nomenclature officielle CERFA).
    'situation_avant_contrat' => [
        'scolaire' => 'Scolaire',
        'prepa_apprentissage' => 'Prépa apprentissage',
        'etudiant' => 'Étudiant',
        'apprentissage' => 'Contrat d\'apprentissage',
        'professionnalisation' => 'Contrat de professionnalisation',
        'contrat_aide' => 'Contrat aidé',
        'stagiaire_avant_apprentissage' => 'En formation au CFA sous statut de stagiaire de la formation professionnelle, avant conclusion d\'un contrat d\'apprentissage',
        'stagiaire_apres_rupture' => 'En formation, au CFA sans contrat sous statut de stagiaire de la formation professionnelle, à la suite d\'une rupture d\'un précédent contrat',
        'stagiaire_autre' => 'Autres situations sous statut de stagiaire de la formation professionnelle',
        'salarie' => 'Salarié',
        'recherche_emploi' => 'Personne à la recherche d\'un emploi (inscrite ou non à France Travail)',
        'inactif' => 'Inactif',
    ],

    // Niveau de la formation visée (cadre national des certifications).
    // Sert au catalogue Formation et à la convention. Valeur = niveau (3-8).
    'niveau_formation' => [
        '3' => 'Niveau 3 — CAP / BEP',
        '4' => 'Niveau 4 — Baccalauréat',
        '5' => 'Niveau 5 — Bac +2',
        '6' => 'Niveau 6 — Bac +3 / Bac +4',
        '7' => 'Niveau 7 — Bac +5',
        '8' => 'Niveau 8 — Doctorat',
        'autre' => 'Autre',
    ],

    // Niveau du diplôme (obtenu ou préparé) — nomenclature officielle CERFA.
    'niveau_diplome' => [
        'bac_5_plus' => 'Diplôme ou titre de niveau bac +5 et plus',
        'bac_3_4' => 'Diplôme ou titre de niveau bac +3 et 4',
        'bac_2' => 'Diplôme ou titre de niveau bac +2',
        'bac' => 'Diplôme ou titre de niveau bac',
        'cap_bep' => 'Diplôme ou titre de niveau CAP/BEP',
        'aucun' => 'Aucun diplôme ni titre',
    ],

    // Diplôme / titre (le plus élevé obtenu, dernier préparé ou visé) — table
    // « diplômes et titres de l'apprenti » de la notice officielle CERFA
    // (n°51649#09, page 3). Chaque clé correspond à un code officiel unique
    // (voir App\Cerfa\CerfaApprentissage::codeDiplome), regroupée par niveau
    // pour rester lisible dans les listes déroulantes.
    'diplome' => [
        'Niveau bac +5 et plus' => [
            'doctorat' => 'Doctorat',
            'master' => 'Master',
            'diplome_ingenieur' => 'Diplôme d\'ingénieur',
            'ecole_commerce' => 'Diplôme d\'école de commerce',
            'autre_bac5' => 'Autre diplôme ou titre de niveau bac +5 ou plus',
        ],
        'Niveau bac +3 et 4' => [
            'licence_pro' => 'Licence professionnelle',
            'licence_generale' => 'Licence générale',
            'but' => 'Bachelor universitaire de technologie (BUT)',
            'autre_bac34' => 'Autre diplôme ou titre de niveau bac +3 ou 4',
        ],
        'Niveau bac +2' => [
            'bts' => 'Brevet de technicien supérieur (BTS)',
            'dut' => 'Diplôme universitaire de technologie (DUT)',
            'autre_bac2' => 'Autre diplôme ou titre de niveau bac +2',
        ],
        'Niveau bac' => [
            'bac_pro' => 'Baccalauréat professionnel',
            'bac_general' => 'Baccalauréat général',
            'bac_techno' => 'Baccalauréat technologique',
            'dsp' => 'Diplôme de spécialisation professionnelle',
            'autre_bac' => 'Autre diplôme ou titre de niveau bac',
        ],
        'Niveau CAP/BEP' => [
            'cap' => 'CAP',
            'bep' => 'BEP',
            'certificat_specialisation' => 'Certificat de spécialisation (ex-mention complémentaire)',
            'autre_cap_bep' => 'Autre diplôme ou titre de niveau CAP/BEP',
        ],
        'Aucun diplôme ni titre' => [
            'brevet' => 'Diplôme national du Brevet',
            'cfg' => 'Certificat de formation générale',
            'aucun' => 'Aucun diplôme ni titre professionnel',
        ],
    ],

    // Dernière classe ou année suivie (nomenclature officielle CERFA).
    'derniere_classe' => [
        'derniere_annee_diplome' => 'Vous avez suivi la dernière année et avez obtenu le diplôme',
        'cycle_1_validee' => 'Vous avez suivi la 1ère année du cycle et l\'avez validée',
        'cycle_1_non_validee' => 'Vous avez suivi la 1ère année du cycle mais ne l\'avez pas validée',
        'cycle_2_validee' => 'Vous avez suivi la 2ème année du cycle et l\'avez validée',
        'cycle_2_non_validee' => 'Vous avez suivi la 2ème année du cycle mais ne l\'avez pas validée',
        'cycle_3_validee' => 'Vous avez suivi la 3ème année du cycle et l\'avez validée',
        'cycle_3_non_validee' => 'Vous avez suivi la 3ème année du cycle mais ne l\'avez pas validée',
        'premier_cycle_college' => 'Vous avez achevé le 1er cycle au collège',
        'interrompu_3eme' => 'Vous avez interrompu vos études en 3ème',
        'interrompu_4eme' => 'Vous avez interrompu vos études en 4ème',
    ],

    // Modalités de suivi (repris de l'enum ModaliteSuivi, exposé ici pour cohérence).
    'annee_cycle' => [
        '1' => '1ère année',
        '2' => '2ème année',
        '3' => '3ème année',
        '4' => '4ème année',
    ],

    'duree_diplome' => [
        'jusqu_1_an' => 'Jusqu\'à 1 an',
        'jusqu_2_ans' => 'Jusqu\'à 2 ans',
        'jusqu_3_ans' => 'Jusqu\'à 3 ans',
        'jusqu_4_ans' => 'Jusqu\'à 4 ans',
    ],

    /* ------------------------------------------------------------------
     |  Employeur (onglet Entreprise)
     * ------------------------------------------------------------------ */

    // Type d'employeur (nomenclature CERFA) — liste unique, regroupée par secteur
    // (sous-titres Privé / Public) pour rester lisible tout en s'affichant d'un bloc.
    'type_employeur' => [
        'Employeur privé' => [
            'entreprise_rcs' => 'Entreprise inscrite au RCS',
            'entreprise_rm' => 'Entreprise inscrite au répertoire des métiers',
            'profession_liberale' => 'Profession libérale',
            'association' => 'Association',
            'employeur_msa' => 'Employeur dont les salariés relèvent de la MSA',
            'autre_prive' => 'Autre employeur privé',
        ],
        'Employeur public' => [
            'etat' => 'Service de l\'État (administrations centrales et leurs services déconcentrés de la fonction publique d\'État)',
            'commune' => 'Commune',
            'departement' => 'Département',
            'region' => 'Région',
            'etablissement_public_hospitalier' => 'Établissement public hospitalier',
            'etablissement_public_enseignement' => 'Établissement public local d\'enseignement',
            'etablissement_public_administratif_etat' => 'Établissement public administratif de l\'État',
            'etablissement_public_administratif_local' => 'Établissement public administratif local (y compris établissement public de coopération intercommunale EPCI)',
            'autre_public' => 'Autre employeur public',
            'etablissement_public_ic' => 'Établissement public industriel et commercial',
        ],
    ],

    // Employeur spécifique (nomenclature CERFA) — liste indépendante du secteur.
    'type_employeur_specifique' => [
        'travail_temporaire' => 'Entreprise de travail temporaire',
        'groupement_employeurs' => 'Groupement d\'employeurs',
        'saisonnier' => 'Employeur saisonnier',
        'apprentissage_familial' => 'Apprentissage familial : l\'employeur est un ascendant de l\'apprenti',
        'aucun' => 'Aucun de ces cas',
    ],

    'adresse_facturation_type' => [
        'convention' => 'Adresse de la convention',
        'siege' => 'Adresse du siège social',
        'personnalisee' => 'Adresse personnalisée',
    ],

    'modalite_envoi_facture' => [
        'email' => 'Par e-mail',
        'courrier' => 'Par courrier',
    ],

    'informations_annexes' => [
        'aucune' => 'Aucune',
        'bon_commande' => 'N° de bon de commande à mentionner sur la facture',
        'autres' => 'Autre(s)',
    ],

    /* ------------------------------------------------------------------
     |  Contrat (onglet Contrat)
     * ------------------------------------------------------------------ */

    // Mode contractuel de l'apprentissage (CERFA rubrique en tête de formulaire,
    // codes 1-4 de la notice n°51649#09, page 1). Mapping dans
    // App\Cerfa\CerfaApprentissage::codeModeContractuel.
    'mode_contractuel' => [
        'duree_limitee' => 'Contrat à durée limitée (CDD)',
        'cdi' => 'Contrat à durée indéterminée (CDI)',
        'travail_temporaire' => 'Entreprise de travail temporaire',
        'saisonnier_deux_employeurs' => 'Activités saisonnières à deux employeurs',
    ],

    // Nature du contrat ou de l'avenant (CERFA rubrique « Type de contrat ou
    // d'avenant »). Libellés et codes issus de la notice officielle
    // n°51649#09 (pages 5-6) ; mapping des codes dans
    // App\Cerfa\CerfaApprentissage::codeTypeContrat.
    'nature_contrat' => [
        'Contrat initial' => [
            'premier_contrat' => 'Premier contrat d\'apprentissage de l\'apprenti',
        ],
        'Succession de contrats' => [
            'succession_meme_employeur' => 'Nouveau contrat avec un apprenti qui a terminé son précédent contrat auprès du même employeur',
            'succession_autre_employeur' => 'Nouveau contrat avec un apprenti qui a terminé son précédent contrat auprès d\'un autre employeur',
            'succession_apres_rupture' => 'Nouveau contrat avec un apprenti dont le précédent contrat a été rompu',
        ],
        'Avenant : modification des conditions du contrat' => [
            'avenant_situation_juridique' => 'Modification de la situation juridique de l\'employeur',
            'avenant_changement_employeur_saisonnier' => 'Changement d\'employeur dans le cadre d\'un contrat saisonnier',
            'avenant_prolongation_echec' => 'Prolongation du contrat suite à un échec à l\'examen de l\'apprenti',
            'avenant_prolongation_rqth' => 'Prolongation du contrat suite à la reconnaissance de l\'apprenti comme travailleur handicapé',
            'avenant_diplome_supplementaire' => 'Diplôme supplémentaire préparé par l\'apprenti (art. L. 6222-22-1)',
            'avenant_autres' => 'Autres changements (maître d\'apprentissage, durée hebdomadaire, réduction de durée, etc.)',
            'avenant_lieu_execution' => 'Modification du lieu d\'exécution du contrat',
            'avenant_lieu_formation' => 'Modification du lieu principal de réalisation de la formation théorique',
        ],
    ],

    // Type de dérogation (CERFA rubrique « Type de dérogation »). Codes issus
    // de la notice officielle n°51649#09 (page 6) ; mapping dans
    // App\Cerfa\CerfaApprentissage::codeDerogation.
    'type_derogation' => [
        'age_inf_16' => 'Âge de l\'apprenti inférieur à 16 ans',
        'age_sup_29' => 'Âge supérieur à 29 ans : cas spécifiques prévus dans le code du travail',
        'reduction_duree' => 'Réduction de la durée du contrat ou de la période d\'apprentissage',
        'allongement_duree' => 'Allongement de la durée du contrat ou de la période d\'apprentissage',
        'cumul' => 'Cumul de dérogations',
        'autre' => 'Autre dérogation',
    ],

    // Base de calcul de la rémunération par année.
    'base_remuneration' => [
        'smic' => 'SMIC',
        'smc' => 'SMC (salaire minimum conventionnel)',
    ],

    // Caisses de retraite complémentaire (principaux groupes de protection
    // sociale). Liste indicative, complétée par « Autre » (saisie libre).
    'caisse_retraite' => [
        'AGIRC-ARRCO' => 'Agirc-Arrco',
        'Malakoff Humanis' => 'Malakoff Humanis',
        'AG2R La Mondiale' => 'AG2R La Mondiale',
        'Klesia' => 'Klesia',
        'Audiens' => 'Audiens',
        'IRP Auto' => 'IRP Auto',
        'Pro BTP' => 'Pro BTP',
        'Lourmel' => 'Lourmel',
        'Autre' => 'Autre',
    ],

    // Type de premier équipement pédagogique. NB : ce n'est PAS une rubrique du
    // CERFA (absente de la notice) mais un repère interne pour les frais annexes
    // pris en charge par l'OPCO (premier équipement).
    'type_equipement' => [
        'lien_formation' => 'Équipement en lien avec la formation de l\'apprenti',
        'informatique' => 'Équipement informatique',
        'outillage' => 'Outillage / équipement professionnel',
        'protection' => 'Équipement de protection individuelle',
        'autre' => 'Autre',
    ],

];
