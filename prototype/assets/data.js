/* =========================================================================
   ERP CFA — Données de la maquette (FICTIVES) — version "métier détaillée".
   Chaque département dispose de ses outils du quotidien : pipelines (kanban),
   graphiques, grille de présences, matrice de permissions, et FICHES CLIQUABLES
   (cliquer une ligne de tableau ouvre un tiroir à onglets).
   Contenu fidèle au cahier des charges (CDC v2). Tout est statique.

   Blocs : subheading | actions | tags | statuses | fields | table | list |
           checklist | rules | chart | kanban | matrix | grid
   Ligne de tableau : ["txt", {b,t}]  OU  { cells:[...], detail:{title,tabs:[{name,blocks:[]}]} }
   ========================================================================= */
window.ERP_DATA = {
  meta: { date: "30/06/2026" },
  parcours: ["Candidat","Entreprise","Besoin","Matching","Admission","Documents","Contrat","OPCO","Assiduité","Service fait","Finance","Qualité","Dashboard"],

  espaces: [

    /* ============================================================ ACCUEIL */
    {
      id:"accueil", label:"Accueil", icon:"🏠", role:"Tous",
      title:"L'ERP qui pilote tout le cycle de vie de l'apprenant",
      tagline:"Une seule plateforme pour tous les départements du CFA — du premier contact commercial jusqu'à la sortie de formation.",
      intro:"Fini les logiciels multiples et les fichiers Excel dispersés : une source unique de vérité, moderne et intelligente.",
      special:"accueil",
      stats:[
        {label:"Départements unifiés",value:"10"},
        {label:"Modules métier",value:"15+"},
        {label:"Source unique de vérité",value:"100 %"},
        {label:"Cash sécurisé",value:"85 %"},
      ],
      pillars:[
        {icon:"💸",title:"Protège le financement",text:"Aucun OPCO ni service fait perdu : alertes proactives et « cash à risque » suivi en continu."},
        {icon:"🏅",title:"Qualiopi sans douleur",text:"Les preuves se constituent au fil de l'eau ; pack d'audit prêt à tout moment."},
        {icon:"📉",title:"Anticipe les ruptures",text:"Un score de risque de décrochage pour agir avant la rupture."},
        {icon:"⚡",title:"Pilotage actionnable",text:"Chaque chiffre est cliquable et mène directement à la prochaine action."},
      ]
    },

    /* ========================================================== DIRECTION */
    {
      id:"direction", label:"Direction", icon:"📊", role:"Direction",
      title:"Tableau de bord — Direction",
      intro:"Vision globale de l'activité, des risques et du cash attendu. Chaque indicateur est cliquable (version finale) et mène à la liste des dossiers concernés.",
      kpis:[
        {label:"Candidats actifs",value:"128",sub:"+12 ce mois",tone:"blue"},
        {label:"Candidats qualifiés",value:"74",sub:"dossier complet",tone:"blue"},
        {label:"Besoins ouverts",value:"15",sub:"postes",tone:"blue"},
        {label:"Matchs en cours",value:"11",sub:"attente retour",tone:"blue"},
        {label:"Contrats signés",value:"37",sub:"objectif 50",tone:"green"},
        {label:"Contrats en préparation",value:"14",sub:"",tone:"amber"},
        {label:"OPCO déposés",value:"22",sub:"",tone:"blue"},
        {label:"OPCO bloqués",value:"6",sub:"à traiter",tone:"red"},
        {label:"Pièces manquantes",value:"31",sub:"tous dossiers",tone:"amber"},
        {label:"Taux d'assiduité",value:"92 %",sub:"30 j",tone:"green"},
        {label:"Montant attendu",value:"312 k€",sub:"exercice",tone:"blue"},
        {label:"Montant facturé",value:"184,5 k€",sub:"",tone:"blue"},
        {label:"Montant encaissé",value:"151,2 k€",sub:"",tone:"green"},
        {label:"Montant bloqué",value:"48,2 k€",sub:"cash à risque",tone:"red"},
        {label:"Ruptures en cours",value:"2",sub:"accompagnement",tone:"amber"},
        {label:"Alertes qualité",value:"4",sub:"preuves manquantes",tone:"amber"},
      ],
      blocks:[
        {type:"chart",title:"Entonnoir de recrutement",max:128,items:[
          {label:"Candidats",value:128,tone:"blue"},
          {label:"Qualifiés (dossier complet)",value:74,tone:"blue"},
          {label:"Proposés en entreprise",value:52,tone:"amber"},
          {label:"Acceptés",value:41,tone:"green"},
          {label:"Contrats signés",value:37,tone:"green"},
        ]},
        {type:"chart",title:"Suivi financier (€)",max:312000,items:[
          {label:"Attendu",value:312000,display:"312 000 €",tone:"blue"},
          {label:"Facturé",value:184500,display:"184 500 €",tone:"blue"},
          {label:"Encaissé",value:151200,display:"151 200 €",tone:"green"},
          {label:"Bloqué",value:48200,display:"48 200 €",tone:"red"},
        ]},
        {type:"table",title:"Dossiers à risque",
          columns:["Apprenant","Entreprise","Type de risque","Responsable","Statut"],
          rows:[
            {cells:["M. Diallo","TechnoSoft","Rejet OPCO non corrigé","S. Martin",{b:"Bloqué",t:"red"}],
             detail:{title:"M. Diallo — dossier à risque",tabs:[
               {name:"Synthèse",blocks:[
                 {type:"fields",title:"Vue d'ensemble",data:[
                   ["Apprenant","Mamadou Diallo"],["Entreprise","TechnoSoft"],["Formation","BTS SIO"],
                   ["Contrat",{b:"Transmis OPCO",t:"blue"}],["OPCO",{b:"Rejeté",t:"red"}],["Cash à risque","8 000 €"]]},
                 {type:"rules",title:"Pourquoi ce dossier est à risque",items:["Rejet OPCO non corrigé depuis 10 jours.","Financement de 8 000 € bloqué tant que le dossier n'est pas corrigé et redéposé."]}]},
               {name:"Parcours",blocks:[
                 {type:"statuses",title:"Statut OPCO",items:["Déposé","En attente retour OPCO","Rejeté","En correction","Corrigé","Accepté"],current:"Rejeté"},
                 {type:"list",title:"Chronologie",items:[
                   {text:"Contrat signé",meta:"20/05/2026",tone:"green"},
                   {text:"Dossier OPCO déposé",meta:"02/06/2026",tone:"blue"},
                   {text:"Rejet OPCO — motif : pièce manquante (CERFA)",meta:"18/06/2026",tone:"red"},
                   {text:"Tâche de correction créée → S. Martin",meta:"18/06/2026",tone:"amber"}]}]},
             ]}},
            ["L. Bernard","BTP Sud","Absences injustifiées","C. Petit",{b:"Risque rupture",t:"amber"}],
            ["A. Moreau","Cabinet Léa","Contrat non signé > 15 j","A. Roux",{b:"En attente",t:"amber"}],
            ["Y. Fontaine","GreenLog","Service fait non validé","C. Petit",{b:"À valider",t:"blue"}],
          ]},
        {type:"table",title:"Dossiers OPCO bloqués",
          columns:["Apprenant","OPCO","Montant","Motif du blocage","Prochaine action"],
          rows:[
            ["M. Diallo","OPCO EP","8 000 €","Pièce manquante","Corriger + redéposer"],
            ["P. Lambert","AKTO","7 800 €","CERFA incomplet","Compléter CERFA"],
            ["R. Girard","ATLAS","9 100 €","Sans retour 21 j","Relancer OPCO"],
          ]},
        {type:"list",title:"Activité récente",items:[
          {text:"Contrat signé — N. Lefèvre / TechnoSoft",meta:"il y a 2 h",tone:"green"},
          {text:"Dossier OPCO rejeté — M. Diallo (pièce manquante)",meta:"il y a 5 h",tone:"red"},
          {text:"Admission validée — S. Garnier",meta:"hier",tone:"blue"}]},
        {type:"rules",title:"Règles d'affichage",items:[
          "Chaque indicateur est cliquable et mène à la liste des dossiers concernés.",
          "Aucun chiffre décoratif : tout indicateur est relié à des données réelles."]},
      ]
    },

    /* =================================================== INTELLIGENCE ⚡ */
    {
      id:"intelligence", label:"Intelligence & Alertes", icon:"⚡", role:"Direction", tag:"Exclusif",
      title:"Intelligence & Alertes — ce qui nous distingue",
      intro:"Le cœur différenciant de l'ERP, que les outils du marché ne font pas (bien) : protéger le financement, collecter les preuves Qualiopi au fil de l'eau, détecter le risque de rupture et transformer chaque chiffre en action. Tout est alimenté par un moteur de règles.",
      kpis:[
        {label:"Cash sécurisé",value:"263 800 €",sub:"85 % de l'attendu",tone:"green"},
        {label:"Cash à risque",value:"48 200 €",sub:"à sécuriser",tone:"red"},
        {label:"Apprenants sereins",value:"125 / 128",sub:"sans alerte",tone:"green"},
        {label:"Qualiopi couvert",value:"28 / 32",sub:"indicateurs",tone:"green"},
      ],
      blocks:[
        /* --- Ce qui va bien --- */
        {type:"subheading",icon:"✅",text:"Ce qui va bien",note:"L'ERP ne sert pas qu'à voir les problèmes : il sécurise aussi le positif et célèbre les avancées."},
        {type:"chart",title:"Santé du financement (€)",max:312000,items:[
          {label:"Sécurisé (encaissé + accepté)",value:263800,display:"263 800 €",tone:"green"},
          {label:"À risque (à sécuriser)",value:48200,display:"48 200 €",tone:"red"}]},
        {type:"list",title:"Bonnes nouvelles récentes",items:[
          {text:"Contrat signé — N. Lefèvre / TechnoSoft",meta:"il y a 2 h",tone:"green"},
          {text:"OPCO accepté — K. Benali (8 800 €)",meta:"hier",tone:"green"},
          {text:"Paiement encaissé — Cabinet Léa (8 800 €)",meta:"hier",tone:"green"},
          {text:"Service fait validé — Groupe BTS SIO (mai)",meta:"avant-hier",tone:"green"},
          {text:"3 nouveaux indicateurs Qualiopi couverts",meta:"cette semaine",tone:"green"}]},
        {type:"chart",title:"Élan commercial du mois",items:[
          {label:"Contrats signés",value:7,max:12,display:"7 / 12",tone:"green"},
          {label:"OPCO acceptés",value:9,max:12,display:"9 / 12",tone:"green"},
          {label:"Apprenants assidus (>90 %)",value:118,max:128,display:"118 / 128",tone:"green"}]},

        /* --- Protection du financement --- */
        {type:"subheading",icon:"💸",text:"Protection du financement",note:"L'ERP qui défend le cash : aucun financement OPCO ou service fait ne se perd dans l'oubli."},
        {type:"chart",title:"Décomposition du cash à risque (€)",max:48200,items:[
          {label:"OPCO bloqués / rejetés",value:24900,display:"24 900 €",tone:"red"},
          {label:"Service fait non validé",value:14800,display:"14 800 €",tone:"amber"},
          {label:"Factures en retard",value:8500,display:"8 500 €",tone:"amber"}]},
        {type:"table",title:"Cash à risque — par dossier",columns:["Dossier","Montant","Cause","Prochaine action","Responsable"],
          rows:[
            {cells:["M. Diallo","8 000 €",{b:"Rejet OPCO",t:"red"},"Corriger CERFA + redéposer","S. Martin"],
             detail:{title:"M. Diallo — financement à sécuriser",tabs:[
               {name:"Synthèse",blocks:[{type:"fields",data:[["Montant à risque","8 000 €"],["Cause","Rejet OPCO (pièce manquante)"],["Depuis","10 jours"],["Responsable","S. Martin"],["Prochaine action","Corriger le CERFA et redéposer"]]},
                 {type:"rules",title:"Ce que l'ERP fait automatiquement",items:["Bloque le statut financier tant que le rejet n'est pas corrigé.","Crée une tâche de correction avec responsable et la fait remonter ici.","Compte ce montant dans le « cash à risque » de la direction."]}]},
             ]}},
            ["R. Girard","9 100 €",{b:"OPCO sans retour 21 j",t:"amber"},"Relancer l'OPCO","S. Martin"],
            ["Groupe CAP-1","14 800 €",{b:"Service fait non validé",t:"amber"},"Valider la période d'avril","C. Petit"],
            ["BTP Sud","8 500 €",{b:"Facture en retard",t:"amber"},"Relancer le paiement","Finance"],
          ]},
        {type:"rules",title:"Pourquoi c'est mieux que le marché",items:["Chez la plupart des ERP, le suivi OPCO est manuel : un rejet oublié = financement perdu. Ici, tout rejet déclenche une action et remonte en « cash à risque »."]},

        /* --- Centre d'alertes proactives --- */
        {type:"subheading",icon:"🚨",text:"Centre d'alertes proactives",note:"Un moteur de règles surveille les dossiers en continu et crée tâches & alertes avant que ça devienne un problème."},
        {type:"table",title:"Alertes générées automatiquement",columns:["Priorité","Alerte","Règle déclenchée","Objet","Action proposée"],
          rows:[
            ["🔴 Haute","Rejet OPCO à traiter","OPCO = Rejeté → action de correction","M. Diallo","Corriger + redéposer"],
            ["🔴 Haute","Risque de rupture","≥ 3 absences injustifiées / 30 j","L. Bernard","Entretien + informer tuteur"],
            ["🟠 Moyenne","OPCO sans retour","Déposé depuis > 21 j","R. Girard","Relancer l'OPCO"],
            ["🟠 Moyenne","Service fait à valider","Période non validée en fin de mois","Groupe CAP-1","Valider la période"],
            ["🟠 Moyenne","Contrat à signer","Envoyé pour signature > 15 j","A. Moreau","Relancer l'employeur"],
            ["🟡 Basse","Pièce manquante","Dossier incomplet > 7 j","E. Rousseau","Relancer le candidat"],
          ]},
        {type:"rules",items:["Les règles sont configurables (table de règles) : on ajuste les seuils sans redévelopper.","Chaque alerte est reliée à un dossier réel et propose la prochaine action."]},

        /* --- Score de risque de rupture --- */
        {type:"subheading",icon:"📉",text:"Score de risque de rupture",note:"Détecter le décrochage avant la rupture. V1/P1 : calcul par règles. P2 : modèle prédictif."},
        {type:"table",title:"Apprenants à surveiller",columns:["Apprenant","Score","Niveau","Facteurs principaux","Action"],
          rows:[
            {cells:["L. Bernard","78 / 100",{b:"Élevé",t:"red"},"4 absences inj. · assiduité 81 % · retard","Entretien + tuteur"],
             detail:{title:"L. Bernard — score de risque",tabs:[
               {name:"Score",blocks:[
                 {type:"fields",data:[["Score de risque","78 / 100"],["Niveau",{b:"Élevé",t:"red"}],["Tendance","En hausse"]]},
                 {type:"chart",title:"Facteurs du score",max:100,items:[
                   {label:"Absences injustifiées",value:40,tone:"red"},
                   {label:"Baisse d'assiduité",value:25,tone:"amber"},
                   {label:"Retards répétés",value:13,tone:"amber"}]},
                 {type:"rules",items:["Score calculé par règles explicables (pas de boîte noire).","Au-delà de 70, l'ERP crée une alerte et propose un accompagnement."]}]},
             ]}},
            ["S. Garnier","42 / 100",{b:"Moyen",t:"amber"},"1 absence inj. · assiduité 95 %","Surveiller"],
            ["T. Mercier","85 / 100",{b:"Élevé",t:"red"},"Abandon en cours · accompagnement","Recherche employeur"],
            ["N. Lefèvre","8 / 100",{b:"Faible",t:"green"},"Assiduité 98 %","—"],
          ]},

        /* --- Qualiopi temps réel --- */
        {type:"subheading",icon:"🏅",text:"Qualiopi en temps réel",note:"Les preuves se constituent au fil du parcours : prêt pour l'audit à tout moment, sans ressaisie."},
        {type:"chart",title:"Couverture des 32 indicateurs",max:32,items:[
          {label:"Couverts (preuve validée)",value:28,tone:"green"},
          {label:"Manquants",value:4,tone:"red"}]},
        {type:"list",title:"À compléter avant audit",items:[
          {text:"Ind. 11 — évaluations manquantes",meta:"resp. C. Petit",tone:"red"},
          {text:"Ind. 22 — enquête de satisfaction",meta:"resp. A. Roux",tone:"red"}]},
        {type:"actions",title:"Pack d'audit",items:["Exporter le pack de preuves (prêt à 88 %)","Voir les preuves manquantes","Générer le rapport Qualiopi"]},

        /* --- Comparatif marché --- */
        {type:"subheading",icon:"✨",text:"Pourquoi c'est unique",note:"Comparatif des capacités clés (estimation marché — à valider avec des avis récents)."},
        {type:"matrix",title:"Nous vs le marché",legend:"✓ fort · ◐ partiel · – faible ou absent — estimation, à valider",
          columns:["Ypareo","Digiforma","Dendreo","Nous"],
          rows:[
            {label:"CFA / apprentissage natif",values:["true","p","p","true"]},
            {label:"UX moderne & simple",values:["false","true","true","true"]},
            {label:"Plateforme unifiée (anti-silos)",values:["p","p","p","true"]},
            {label:"Protection active du financement",values:["p","false","false","true"]},
            {label:"Qualiopi au fil de l'eau",values:["p","true","p","true"]},
            {label:"Score de risque de rupture",values:["false","false","false","true"]},
            {label:"Pilotage actionnable",values:["p","p","p","true"]},
            {label:"Règles / statuts configurables",values:["false","p","p","true"]},
          ]},
        {type:"rules",title:"Lucidité",items:["Les leaders gardent l'avantage sur les connecteurs réglementaires (DECA/EDOF/API OPCO) et le LMS : c'est notre cap P2, pas un combat de V1."]},
      ]
    },

    /* ========================================================= COMMERCIAL */
    {
      id:"commercial", label:"Commercial", icon:"🤝", role:"Commercial",
      title:"Espace commercial",
      intro:"Vos candidats, entreprises, besoins et le matching, avec pipeline, relances, rendez-vous et objectifs.",
      kpis:[
        {label:"Mes candidats",value:"42",sub:"à suivre",tone:"blue"},
        {label:"Leads chauds",value:"9",sub:"à rappeler",tone:"amber"},
        {label:"Besoins ouverts",value:"15",sub:"postes",tone:"green"},
        {label:"Matchs en cours",value:"11",sub:"attente retour",tone:"blue"},
      ],
      blocks:[
        /* --- Candidats --- */
        {type:"subheading",icon:"👤",text:"Candidats",note:"Centraliser toutes les informations relatives aux prospects et candidats."},
        {type:"kanban",title:"Pipeline candidats",columns:[
          {title:"Dossier incomplet",cards:[{title:"Emma Rousseau",sub:"BTS MCO",badge:{b:"Relance J+9",t:"red"}},{title:"T. Mercier",sub:"CAP"}]},
          {title:"Dossier complet",cards:[{title:"Karim Benali",sub:"Bac Pro Commerce",badge:{b:"Qualifié",t:"green"}}]},
          {title:"En recherche d'entreprise",cards:[{title:"Sophie Garnier",sub:"BTS SIO"},{title:"Lucas Petit",sub:"CAP Cuisine"}]},
          {title:"Contrat signé",cards:[{title:"N. Lefèvre",sub:"TechnoSoft",badge:{b:"Signé",t:"green"}}]},
        ]},
        {type:"chart",title:"Mes objectifs du mois",items:[
          {label:"Contrats signés",value:7,max:12,display:"7 / 12",tone:"green"},
          {label:"RDV entreprises",value:14,max:20,display:"14 / 20",tone:"blue"},
          {label:"Candidats qualifiés",value:18,max:25,display:"18 / 25",tone:"amber"},
        ]},
        {type:"list",title:"Mes rendez-vous à venir",items:[
          {text:"Entretien Sophie Garnier ↔ TechnoSoft",meta:"03/07 · 14 h",tone:"blue"},
          {text:"Visite entreprise — GreenLog",meta:"04/07 · 10 h",tone:"blue"},
          {text:"Appel de relance — Emma Rousseau",meta:"aujourd'hui · 16 h",tone:"amber"}]},
        {type:"actions",items:["Créer un candidat","Modifier","Rechercher","Filtrer (statut, formation, source, commercial, période)","Notes internes","Tâche de relance","Historique des échanges","Documents manquants","Entreprise associée","État admission","État du contrat"]},
        {type:"statuses",title:"Statuts du candidat",items:["Dossier incomplet","Dossier complet","En recherche d'entreprise","Contrat signé","Rupture"],current:"En recherche d'entreprise"},
        {type:"table",title:"Candidats à relancer",
          columns:["Candidat","Formation visée","Source","Dernier contact","Statut"],
          rows:[
            {cells:["Sophie Garnier","BTS SIO","Salon",{b:"5 j",t:"amber"},{b:"En recherche d'entreprise",t:"blue"}],
             detail:{title:"Sophie Garnier — fiche candidat",tabs:[
               {name:"Infos",blocks:[{type:"fields",title:"Informations",data:[
                 ["Nom","Garnier"],["Prénom","Sophie"],["Email","s.garnier@email.fr"],["Téléphone","06 12 34 56 78"],
                 ["Date de naissance","14/03/2004"],["Adresse","12 rue des Lilas, 31000 Toulouse"],["Formation visée","BTS SIO"],
                 ["Niveau actuel","Bac"],["Mobilité","30 km"],["Disponibilité","Septembre 2026"],["Source","Salon"],["Commercial","A. Roux"],
                 ["Statut",{b:"En recherche d'entreprise",t:"blue"}]]}]},
               {name:"Documents",blocks:[{type:"table",title:"Documents du candidat",columns:["Document","Type","Version","Statut"],rows:[
                 ["CV_Garnier.pdf","CV candidat","v2",{b:"Reçu",t:"green"}],
                 ["Test_posit.pdf","Test de positionnement","v1",{b:"Reçu",t:"green"}],
                 ["CNI.pdf","Pièce d'identité","v1",{b:"Reçu",t:"green"}],
                 ["Justif_domicile","Justificatif","—",{b:"En attente",t:"amber"}]]}]},
               {name:"Historique",blocks:[{type:"list",title:"Échanges",items:[
                 {text:"Appel — intéressée par TechnoSoft",meta:"25/06",tone:"blue"},
                 {text:"CV envoyé à TechnoSoft",meta:"26/06",tone:"green"},
                 {text:"Entretien planifié",meta:"03/07",tone:"blue"}]}]},
               {name:"Tâches",blocks:[{type:"checklist",title:"Tâches de relance",items:[
                 {text:"Relancer pour justificatif de domicile",state:"À faire",tone:"amber"},
                 {text:"Confirmer l'entretien du 03/07",state:"À faire",tone:"blue"},
                 {text:"Préparer la fiche de présentation",state:"Terminée",tone:"green"}]}]},
             ]}},
            {cells:["Karim Benali","Bac Pro Commerce","Site web",{b:"2 j",t:"green"},{b:"Dossier complet",t:"green"}],
             detail:{title:"Karim Benali — fiche candidat",tabs:[
               {name:"Infos",blocks:[{type:"fields",data:[["Nom","Benali"],["Prénom","Karim"],["Email","k.benali@email.fr"],["Téléphone","07 88 99 00 11"],["Formation visée","Bac Pro Commerce"],["Statut",{b:"Dossier complet",t:"green"}]]}]},
               {name:"Documents",blocks:[{type:"table",columns:["Document","Statut"],rows:[["CV","Reçu"],["Diplôme",{b:"Manquant",t:"red"}]]}]},
             ]}},
            ["Emma Rousseau","BTS MCO","Recommandation",{b:"9 j",t:"red"},{b:"Dossier incomplet",t:"red"}],
            ["Lucas Petit","CAP Cuisine","Pôle Emploi",{b:"1 j",t:"green"},{b:"En recherche d'entreprise",t:"blue"}],
          ]},
        {type:"rules",items:["Un candidat doit avoir au minimum un moyen de contact : email ou téléphone.","Un candidat ne peut pas passer à « Dossier complet » si des pièces obligatoires sont manquantes."]},

        /* --- Entreprises --- */
        {type:"subheading",icon:"🏢",text:"Entreprises",note:"Gérer entreprises, contacts, tuteurs et besoins en alternance."},
        {type:"actions",items:["Créer une entreprise","Ajouter des contacts","Ajouter un tuteur","Créer un besoin","Voir les contrats rattachés","Voir les candidats proposés","Historique","Notes & comptes rendus","Suivi satisfaction / incidents"]},
        {type:"table",title:"Entreprises",columns:["Raison sociale","Secteur","OPCO","Contact principal","Statut"],
          rows:[
            {cells:["TechnoSoft SAS","Informatique","ATLAS","M. Dupont",{b:"Partenaire active",t:"green"}],
             detail:{title:"TechnoSoft SAS — fiche entreprise",tabs:[
               {name:"Infos",blocks:[{type:"fields",data:[
                 ["Raison sociale","TechnoSoft SAS"],["Nom commercial","TechnoSoft"],["SIRET","812 345 678 00021"],
                 ["Adresse","5 av. de l'Innovation, 31670 Labège"],["Secteur","Informatique / Édition logicielle"],["OPCO","ATLAS"],
                 ["Statut",{b:"Partenaire active",t:"green"}]]}]},
               {name:"Contacts & tuteurs",blocks:[{type:"table",columns:["Nom","Fonction","Rôle"],rows:[
                 ["M. Dupont","DRH",{b:"Contact principal",t:"blue"}],["Mme Léa Robin","Lead Dev",{b:"Tuteur",t:"green"}]]}]},
               {name:"Besoins & contrats",blocks:[
                 {type:"table",title:"Besoins",columns:["Poste","Postes","Statut"],rows:[["Développeur web","2",{b:"Profils envoyés",t:"blue"}]]},
                 {type:"table",title:"Contrats",columns:["Apprenant","Statut"],rows:[["N. Lefèvre",{b:"Transmis OPCO",t:"blue"}]]}]},
               {name:"Historique",blocks:[{type:"list",items:[{text:"Convention de partenariat signée",meta:"2024",tone:"green"},{text:"3 alternants placés depuis 2024",meta:"",tone:"blue"}]}]},
             ]}},
            ["Cabinet Léa","Conseil","AKTO","Mme Léa B.",{b:"En relation",t:"blue"}],
            ["GreenLog","Logistique","OPCO 2i","M. Sahli",{b:"Prospect",t:"amber"}],
            ["BTP Sud","Bâtiment","Constructys","M. Roca",{b:"Partenaire active",t:"green"}],
          ]},
        {type:"rules",items:["Une entreprise ne peut pas avoir de contrat actif sans contact principal.","Un tuteur doit être rattaché à une entreprise.","Un besoin doit être rattaché à une entreprise existante."]},

        /* --- Besoins --- */
        {type:"subheading",icon:"📌",text:"Besoins entreprises",note:"Suivre les postes ouverts pour recruter des alternants."},
        {type:"actions",items:["Créer un besoin","Formation visée","Poste recherché","Nombre de postes","Associer contact / tuteur","Rattacher des candidats","Suivre les candidats proposés","Clôturer le besoin"]},
        {type:"statuses",title:"Statuts du besoin",items:["Besoin créé","En qualification","Profils recherchés","Profils envoyés","Entretien entreprise prévu","Candidat retenu","Besoin pourvu","Annulé","Archivé"],current:"Profils envoyés"},
        {type:"table",title:"Besoins ouverts",columns:["Entreprise","Poste","Formation","Postes","Statut"],
          rows:[
            {cells:["TechnoSoft","Développeur web","BTS SIO","2",{b:"Profils envoyés",t:"blue"}],
             detail:{title:"Besoin — Développeur web (TechnoSoft)",tabs:[
               {name:"Infos",blocks:[{type:"fields",data:[
                 ["Entreprise","TechnoSoft"],["Intitulé du poste","Développeur web"],["Formation visée","BTS SIO"],["Localisation","Labège (31)"],
                 ["Date de démarrage","01/09/2026"],["Nombre de postes","2"],["Rythme","2 j école / 3 j entreprise"],["Prérequis","Bases HTML/CSS"],
                 ["Contact responsable","M. Dupont"],["Tuteur prévu","Mme Léa Robin"],["Statut",{b:"Profils envoyés",t:"blue"}]]}]},
               {name:"Candidats proposés",blocks:[{type:"table",columns:["Candidat","CV envoyé","Statut"],rows:[
                 ["Sophie Garnier",{b:"Oui",t:"green"},{b:"En attente de retour",t:"blue"}],
                 ["Lucas Petit",{b:"Oui",t:"green"},{b:"Entretien prévu",t:"blue"}],
                 ["Karim Benali",{b:"Oui",t:"green"},{b:"Refusé par l'entreprise",t:"red"}]]}]},
             ]}},
            ["Cabinet Léa","Assistant commercial","BTS MCO","1",{b:"En qualification",t:"amber"}],
            ["GreenLog","Logisticien","Bac Pro Log.","3",{b:"Entretien entreprise prévu",t:"blue"}],
            ["BTP Sud","Maçon","CAP","1",{b:"Candidat retenu",t:"green"}],
          ]},

        /* --- Matching --- */
        {type:"subheading",icon:"🔗",text:"Matching candidat / entreprise",note:"Rapprocher les candidats disponibles avec les besoins entreprises."},
        {type:"actions",items:["Rechercher les candidats compatibles","Proposer un candidat","Rattacher au besoin","Indiquer CV envoyé","Suivre les retours","Suivre les entretiens","Marquer accepté / refusé","Historique des propositions"]},
        {type:"kanban",title:"Pipeline matching — besoin « Développeur web »",columns:[
          {title:"Proposé",cards:[{title:"Emma Rousseau",sub:"BTS MCO"}]},
          {title:"CV envoyé",cards:[{title:"Sophie Garnier",sub:"BTS SIO"}]},
          {title:"Entretien prévu",cards:[{title:"Lucas Petit",sub:"03/07"}]},
          {title:"Accepté",cards:[]},
          {title:"Refusé",cards:[{title:"Karim Benali",sub:"non retenu",badge:{b:"Refusé entreprise",t:"red"}}]},
        ]},
        {type:"statuses",title:"Statuts du matching",items:["Proposé","CV envoyé","Entretien prévu","En attente de retour","Accepté","Refusé par l'entreprise","Refusé par le candidat","Abandonné"],current:"En attente de retour"},
        {type:"rules",items:["Un candidat ne peut pas être marqué « Accepté » sur un besoin déjà clôturé.","Un besoin doit afficher l'ensemble des candidats proposés et leur statut."]},
      ]
    },

    /* ========================================================== ADMISSION */
    {
      id:"admission", label:"Admission", icon:"✅", role:"Admission",
      title:"Espace admission & documents",
      intro:"Contrôle des dossiers, pièces obligatoires, conformité et validation avant passage au contrat ; gestion documentaire (GED).",
      kpis:[
        {label:"Dossiers incomplets",value:"18",sub:"pièces manquantes",tone:"red"},
        {label:"À vérifier",value:"7",sub:"en attente",tone:"amber"},
        {label:"Validés",value:"24",sub:"ce mois",tone:"green"},
        {label:"Refusés",value:"3",sub:"ce mois",tone:"gray"},
      ],
      blocks:[
        {type:"subheading",icon:"✅",text:"Dossier d'admission",note:"Contrôler les dossiers, vérifier les pièces, valider ou refuser et préparer le passage au contrat."},
        {type:"actions",items:["Ouvrir un dossier","Checklist des pièces obligatoires","Vérifier la conformité","Marquer une pièce non conforme","Valider","Refuser","Préparer le passage au contrat"]},
        {type:"statuses",title:"Statuts du dossier",items:["À vérifier","Incomplet","Non conforme","Validé","Refusé"],current:"Incomplet"},
        {type:"chart",title:"État des dossiers",items:[
          {label:"Validés",value:24,tone:"green"},{label:"À vérifier",value:7,tone:"amber"},
          {label:"Incomplets",value:18,tone:"red"},{label:"Refusés",value:3,tone:"gray"}]},
        {type:"table",title:"Dossiers à traiter",columns:["Candidat","Formation","Pièces manquantes","Statut"],
          rows:[
            {cells:["Karim Benali","Bac Pro Commerce","Diplôme, justif. domicile",{b:"Incomplet",t:"red"}],
             detail:{title:"Karim Benali — dossier d'admission",tabs:[
               {name:"Dossier",blocks:[{type:"fields",data:[
                 ["Candidat","Karim Benali"],["Formation","Bac Pro Commerce"],["Commercial","A. Roux"],
                 ["Pièces reçues","3 / 5"],["Conformité","2 à fournir"],["Statut",{b:"Incomplet",t:"red"}]]}]},
               {name:"Pièces",blocks:[{type:"checklist",title:"Checklist des pièces obligatoires",items:[
                 {text:"Pièce d'identité",state:"Reçu",tone:"green"},
                 {text:"CV candidat",state:"Reçu",tone:"green"},
                 {text:"Test de positionnement",state:"Reçu",tone:"green"},
                 {text:"Justificatif de domicile",state:"En attente",tone:"amber"},
                 {text:"Diplôme / relevé de notes",state:"Manquant",tone:"red"}]}]},
               {name:"Historique",blocks:[{type:"list",items:[
                 {text:"Dossier ouvert",meta:"22/06",tone:"blue"},
                 {text:"Test de positionnement reçu",meta:"24/06",tone:"green"},
                 {text:"Relance pièces envoyée",meta:"28/06",tone:"amber"}]}]},
               {name:"Décision",blocks:[
                 {type:"actions",title:"Actions possibles",items:["Valider le dossier","Refuser le dossier","Marquer une pièce non conforme","Demander un complément"]},
                 {type:"rules",items:["Validation impossible tant que des pièces obligatoires sont manquantes ou non conformes."]}]},
             ]}},
            ["Emma Rousseau","BTS MCO","CNI, CV",{b:"Incomplet",t:"red"}],
            ["Sophie Garnier","BTS SIO","—",{b:"À vérifier",t:"amber"}],
            ["Lucas Petit","CAP Cuisine","—",{b:"Validé",t:"green"}],
          ]},
        {type:"rules",items:["Un dossier ne peut pas être validé tant que des pièces obligatoires sont manquantes ou non conformes."]},

        {type:"subheading",icon:"📎",text:"Documents (GED)",note:"Centraliser tous les fichiers avec gestion des versions et traçabilité."},
        {type:"actions",items:["Importer","Rattacher (candidat, entreprise, contrat, dossier)","Type","Statut","Remplacer","Historique des versions","Rechercher","Télécharger","Voir auteur & date"]},
        {type:"tags",title:"Types de documents",items:["CV candidat","CV maître d'apprentissage","Test de positionnement","Contrat","CERFA","Convention","Calendrier","Justificatif d'absence","Preuve de service fait","Facture","Document qualité","Autre"]},
        {type:"statuses",title:"Statuts du document",items:["En attente","Reçu","Expiré"],current:"Reçu"},
        {type:"table",title:"Documents récents",columns:["Document","Type","Rattaché à","Version","Ajouté par","Statut"],
          rows:[
            ["CV_Garnier.pdf","CV candidat","S. Garnier","v2","A. Roux",{b:"Reçu",t:"green"}],
            ["CERFA_Lefevre.pdf","CERFA","Contrat N. Lefèvre","v1","S. Martin",{b:"Reçu",t:"green"}],
            ["Justif_domicile.pdf","Justificatif","K. Benali","—","—",{b:"En attente",t:"amber"}],
            ["Test_Rousseau.pdf","Test positionnement","E. Rousseau","v1","C. Petit",{b:"Expiré",t:"red"}],
          ]},
        {type:"rules",items:["Un document critique ne doit jamais être supprimé définitivement sans trace.","Lorsqu'un document est remplacé, l'ancienne version reste consultable par les utilisateurs autorisés."]},
      ]
    },

    /* =============================================== ADMINISTRATIF & OPCO */
    {
      id:"administratif", label:"Administratif & OPCO", icon:"📁", role:"Administratif",
      title:"Espace administratif & OPCO",
      intro:"Préparer, suivre et archiver contrats, conventions et CERFA ; gérer le dépôt, le rejet et la correction des dossiers OPCO.",
      kpis:[
        {label:"Contrats à préparer",value:"9",sub:"",tone:"amber"},
        {label:"À signer",value:"5",sub:"envoyés",tone:"blue"},
        {label:"OPCO à déposer",value:"7",sub:"prêts",tone:"amber"},
        {label:"OPCO rejetés",value:"6",sub:"à corriger",tone:"red"},
      ],
      blocks:[
        {type:"subheading",icon:"📝",text:"Contrats & conventions",note:"Préparer, suivre et archiver les contrats d'apprentissage, conventions et CERFA."},
        {type:"actions",items:["Créer un dossier contrat","Rattacher candidat / entreprise / formation","Renseigner les dates","Tuteur & rythme","Vérifier les champs obligatoires","Générer / importer les documents","Suivre la signature","Conserver les versions signées","Envoyer vers l'OPCO"]},
        {type:"statuses",title:"Statuts du contrat",items:["Brouillon","Informations manquantes","Prêt à vérifier","Envoyé pour signature","Signé","Transmis OPCO","Actif","Rompu","Archivé"],current:"Envoyé pour signature"},
        {type:"table",title:"Contrats",columns:["Apprenant","Entreprise","Formation","Signature","Statut contrat"],
          rows:[
            {cells:["A. Moreau","Cabinet Léa","BTS MCO",{b:"En attente",t:"amber"},{b:"Envoyé pour signature",t:"blue"}],
             detail:{title:"A. Moreau — dossier contrat",tabs:[
               {name:"Contrat",blocks:[{type:"fields",data:[
                 ["Candidat","Aline Moreau"],["Entreprise","Cabinet Léa"],["Formation","BTS MCO"],["Code RNCP","RNCP38362"],
                 ["Date de début","01/09/2026"],["Date de fin","31/08/2028"],["Tuteur","Mme Léa B."],["Rythme","2 j / 3 j"],
                 ["Lieu de formation","Campus Toulouse"],["Statut signature",{b:"En attente employeur",t:"amber"}],["Statut contrat",{b:"Envoyé pour signature",t:"blue"}]]}]},
               {name:"Signature",blocks:[
                 {type:"statuses",title:"Avancement",items:["Brouillon","Prêt à vérifier","Envoyé pour signature","Signé","Transmis OPCO"],current:"Envoyé pour signature"},
                 {type:"list",title:"Suivi",items:[
                   {text:"Dossier vérifié — champs complets",meta:"24/06",tone:"green"},
                   {text:"Envoyé pour signature (employeur + apprenti)",meta:"25/06",tone:"blue"},
                   {text:"En attente de la signature employeur",meta:"depuis 5 j",tone:"amber"}]}]},
               {name:"Documents",blocks:[{type:"table",columns:["Document","Type","Statut"],rows:[
                 ["Contrat_Moreau.pdf","Contrat",{b:"En attente signature",t:"amber"}],
                 ["CERFA_Moreau.pdf","CERFA",{b:"Reçu",t:"green"}],
                 ["Convention.pdf","Convention",{b:"Reçu",t:"green"}]]}]},
             ]}},
            ["N. Lefèvre","TechnoSoft","BTS SIO",{b:"Signé",t:"green"},{b:"Transmis OPCO",t:"blue"}],
            ["S. Garnier","GreenLog","Bac Pro Log.",{b:"—",t:"gray"},{b:"Informations manquantes",t:"red"}],
            ["L. Petit","BTP Sud","CAP Cuisine",{b:"—",t:"gray"},{b:"Prêt à vérifier",t:"amber"}],
          ]},
        {type:"rules",items:["Un contrat ne peut pas passer à « Signé » sans document signé associé ou justification.","Toute modification importante après signature doit créer une nouvelle version ou une trace."]},

        {type:"subheading",icon:"🏦",text:"Dossiers OPCO",note:"Suivre la préparation, le dépôt, l'acceptation, le rejet et la correction des financements."},
        {type:"actions",items:["Créer un dossier OPCO (lié au contrat)","Renseigner l'OPCO","Suivre le dépôt","Montant attendu / accepté","Ajouter un motif de rejet","Créer une tâche de correction","Suivre les relances","Voir les dossiers bloqués","Archiver les preuves"]},
        {type:"statuses",title:"Statuts OPCO",items:["Non créé","À préparer","Prêt au dépôt","Déposé","En attente retour OPCO","Accepté","Rejeté","En correction","Corrigé","Clôturé"],current:"Rejeté"},
        {type:"list",title:"Échéances & relances",items:[
          {text:"M. Diallo — corriger le CERFA et redéposer",meta:"en retard",tone:"red"},
          {text:"R. Girard — relancer l'OPCO (sans retour 21 j)",meta:"à faire",tone:"amber"},
          {text:"A. Moreau — préparer le dépôt après signature",meta:"à venir",tone:"blue"}]},
        {type:"table",title:"Dossiers OPCO",columns:["Apprenant","OPCO","Montant prévu","Montant accepté","Statut","Motif rejet"],
          rows:[
            {cells:["M. Diallo","OPCO EP","8 000 €","—",{b:"Rejeté",t:"red"},"Pièce manquante"],
             detail:{title:"M. Diallo — dossier OPCO",tabs:[
               {name:"Dossier",blocks:[{type:"fields",data:[
                 ["Contrat lié","Diallo / TechnoSoft"],["OPCO","OPCO EP"],["Date de dépôt","02/06/2026"],["Statut",{b:"Rejeté",t:"red"}],
                 ["Montant prévu","8 000 €"],["Montant accepté","—"],["Motif de rejet","Pièce manquante (CERFA)"],
                 ["Responsable correction","S. Martin"],["Date de relance","20/06/2026"]]}]},
               {name:"Workflow",blocks:[
                 {type:"statuses",title:"Cycle du dossier",items:["À préparer","Prêt au dépôt","Déposé","En attente retour OPCO","Rejeté","En correction","Corrigé","Accepté"],current:"Rejeté"},
                 {type:"list",title:"Chronologie",items:[
                   {text:"Dossier préparé",meta:"30/05",tone:"blue"},
                   {text:"Déposé à l'OPCO EP",meta:"02/06",tone:"blue"},
                   {text:"Rejeté — pièce manquante (CERFA)",meta:"18/06",tone:"red"},
                   {text:"Tâche de correction créée → S. Martin",meta:"18/06",tone:"amber"}]}]},
               {name:"Impact finance",blocks:[{type:"fields",data:[["Montant bloqué","8 000 €"],["Statut paiement",{b:"Bloqué",t:"red"}],["Motif","Rejet OPCO"]]},
                 {type:"rules",items:["Un rejet OPCO doit obligatoirement avoir un motif.","Un dossier rejeté doit créer une action de correction."]}]},
             ]}},
            ["N. Lefèvre","ATLAS","9 200 €","9 200 €",{b:"Déposé",t:"blue"},"—"],
            ["A. Moreau","AKTO","7 500 €","—",{b:"À préparer",t:"amber"},"—"],
            ["K. Benali","OPCO 2i","8 800 €","8 800 €",{b:"Accepté",t:"green"},"—"],
          ]},
        {type:"rules",items:["Un dossier OPCO ne peut pas être « Prêt au dépôt » si le contrat n'est pas signé.","Un rejet OPCO doit obligatoirement avoir un motif.","Un dossier rejeté doit créer une action de correction."]},
      ]
    },

    /* =============================================== PÉDAGOGIE & SCOLARITÉ */
    {
      id:"pedagogie", label:"Pédagogie & Scolarité", icon:"🎓", role:"Pédagogie",
      title:"Espace pédagogie & scolarité",
      intro:"Sessions, saisie des présences, justificatifs, validation mensuelle du service fait, alertes de décrochage et suivi des ruptures.",
      kpis:[
        {label:"Sessions actives",value:"6",sub:"ce mois",tone:"blue"},
        {label:"Taux d'assiduité",value:"92 %",sub:"BTS SIO",tone:"green"},
        {label:"Absences injustifiées",value:"11",sub:"à traiter",tone:"red"},
        {label:"Périodes à valider",value:"2",sub:"service fait",tone:"amber"},
      ],
      blocks:[
        {type:"subheading",icon:"📅",text:"Scolarité & assiduité",note:"Suivre présences, absences, justificatifs et validations mensuelles."},
        {type:"actions",items:["Créer une session","Rattacher des apprenants","Saisir les présences","Saisir les absences","Ajouter un justificatif","Qualifier une absence","Assiduité par apprenant","Assiduité par groupe","Valider une période","Générer une preuve de service fait"]},
        {type:"grid",title:"Présences — Groupe BTS SIO (semaine 20)",legend:"P présent · J absence justifiée · I absence injustifiée · R retard · — non renseigné",
          columns:["Lun","Mar","Mer","Jeu","Ven"],
          rows:[
            {label:"N. Lefèvre",cells:[{v:"P",t:"green"},{v:"P",t:"green"},{v:"P",t:"green"},{v:"P",t:"green"},{v:"P",t:"green"}]},
            {label:"L. Bernard",cells:[{v:"P",t:"green"},{v:"I",t:"red"},{v:"I",t:"red"},{v:"R",t:"amber"},{v:"P",t:"green"}]},
            {label:"S. Garnier",cells:[{v:"P",t:"green"},{v:"P",t:"green"},{v:"J",t:"blue"},{v:"P",t:"green"},{v:"P",t:"green"}]},
            {label:"K. Benali",cells:[{v:"P",t:"green"},{v:"P",t:"green"},{v:"P",t:"green"},{v:"P",t:"green"},{v:"—",t:"gray"}]},
          ]},
        {type:"chart",title:"Assiduité par apprenant (%)",max:100,items:[
          {label:"N. Lefèvre",value:98,display:"98 %",tone:"green"},
          {label:"K. Benali",value:100,display:"100 %",tone:"green"},
          {label:"S. Garnier",value:95,display:"95 %",tone:"green"},
          {label:"L. Bernard",value:81,display:"81 %",tone:"red"}]},
        {type:"statuses",title:"Statuts de présence",items:["Présent","Absent justifié","Absent injustifié","Retard","Départ anticipé","Non renseigné"],current:"Absent injustifié"},
        {type:"table",title:"Suivi d'assiduité — Mai 2026",columns:["Apprenant","Groupe","Présence","Absences inj.","Statut période"],
          rows:[
            ["N. Lefèvre","BTS SIO","98 %","0",{b:"OK",t:"green"}],
            {cells:["L. Bernard","CAP-1","81 %","4",{b:"Alerte décrochage",t:"red"}],
             detail:{title:"L. Bernard — suivi d'assiduité",tabs:[
               {name:"Assiduité",blocks:[
                 {type:"fields",data:[["Apprenant","Léo Bernard"],["Formation","CAP Maçon"],["Groupe","CAP-1"],["Taux d'assiduité","81 %"],["Absences injustifiées","4"],["Statut",{b:"Alerte décrochage",t:"red"}]]},
                 {type:"chart",title:"Évolution mensuelle (%)",max:100,items:[{label:"Mars",value:95,display:"95 %",tone:"green"},{label:"Avril",value:88,display:"88 %",tone:"amber"},{label:"Mai",value:81,display:"81 %",tone:"red"}]}]},
               {name:"Absences",blocks:[{type:"table",columns:["Date","Statut","Justificatif"],rows:[
                 ["07/05",{b:"Absent injustifié",t:"red"},"—"],["12/05",{b:"Absent injustifié",t:"red"},"—"],
                 ["13/05",{b:"Absent injustifié",t:"red"},"—"],["19/05",{b:"Retard",t:"amber"},"—"]]}]},
               {name:"Décrochage",blocks:[{type:"list",title:"Actions de suivi",items:[
                 {text:"Tâche : entretien avec l'apprenant",meta:"resp. C. Petit",tone:"amber"},
                 {text:"Informer l'entreprise (tuteur)",meta:"à faire",tone:"blue"}]},
                 {type:"rules",items:["Une absence injustifiée doit générer une alerte ou une tâche de suivi."]}]},
             ]}},
            ["S. Garnier","BTS MCO","95 %","1",{b:"À suivre",t:"amber"}],
            ["K. Benali","Bac Pro","100 %","0",{b:"OK",t:"green"}],
          ]},
        {type:"rules",items:["Une période ne peut pas être validée si des présences sont non renseignées.","Une absence injustifiée doit générer une alerte ou une tâche de suivi.","Un justificatif doit être rattaché à une absence."]},

        {type:"subheading",icon:"🧾",text:"Service fait",note:"Validation mensuelle de l'assiduité et génération des preuves liées au financement."},
        {type:"statuses",title:"Statut de la période",items:["En cours","À valider","Validée"],current:"À valider"},
        {type:"table",title:"Périodes de service fait",columns:["Groupe","Période","Présences renseignées","Statut"],
          rows:[
            ["BTS SIO","Mai 2026","100 %",{b:"Validée",t:"green"}],
            ["CAP-1","Avril 2026","92 %",{b:"À valider",t:"amber"}],
            ["BTS MCO","Mai 2026","88 %",{b:"En cours",t:"blue"}],
          ]},

        {type:"subheading",icon:"⛔",text:"Ruptures",note:"Suivre les ruptures de contrat, l'accompagnement et les impacts administratifs et financiers."},
        {type:"statuses",title:"Statuts de rupture",items:["Rupture suspectée","Rupture confirmée","Documents en attente","Accompagnement en cours","Recherche nouvel employeur","Nouveau contrat trouvé","Sortie définitive","Clôturé"],current:"Accompagnement en cours"},
        {type:"table",title:"Dossiers de rupture",columns:["Apprenant","Date","Motif","Statut"],
          rows:[
            ["L. Bernard","—","Décrochage (suspecté)",{b:"Rupture suspectée",t:"amber"}],
            ["T. Mercier","15/04/2026","Abandon",{b:"Accompagnement en cours",t:"blue"}],
          ]},
        {type:"rules",items:["Une rupture confirmée doit avoir une date et un motif.","Une rupture doit créer une trace dans le contrat, l'OPCO et la finance.","Les actions d'accompagnement doivent être conservées comme preuves."]},
      ]
    },

    /* ============================================================ FINANCE */
    {
      id:"finance", label:"Finance", icon:"💶", role:"Finance",
      title:"Espace finance",
      intro:"Suivre les montants attendus, facturés, encaissés ou bloqués par dossier, avec échéances, factures et retards.",
      kpis:[
        {label:"Attendu",value:"312 k€",sub:"exercice",tone:"blue"},
        {label:"Facturé",value:"184,5 k€",sub:"",tone:"blue"},
        {label:"Encaissé",value:"151,2 k€",sub:"",tone:"green"},
        {label:"Bloqué",value:"48,2 k€",sub:"à débloquer",tone:"red"},
      ],
      blocks:[
        {type:"subheading",icon:"💶",text:"Suivi financier",note:"Montants attendus, facturés, encaissés ou bloqués par dossier."},
        {type:"chart",title:"Trésorerie (€)",max:312000,items:[
          {label:"Attendu",value:312000,display:"312 000 €",tone:"blue"},
          {label:"Facturé",value:184500,display:"184 500 €",tone:"blue"},
          {label:"Encaissé",value:151200,display:"151 200 €",tone:"green"},
          {label:"Bloqué",value:48200,display:"48 200 €",tone:"red"}]},
        {type:"actions",items:["Créer une ligne financière","Montant attendu / accepté / facturé / encaissé / bloqué","Motif de blocage","Suivre les échéances","Générer / importer une facture","Suivre les paiements","Voir les retards"]},
        {type:"statuses",title:"Statuts de paiement",items:["Prévisionnel","À facturer","Facture préparée","Facturé","Partiellement payé","Payé","En retard","Bloqué","Annulé"],current:"Bloqué"},
        {type:"table",title:"Lignes financières",columns:["Apprenant","Contrat","Attendu","Échéance","Statut"],
          rows:[
            {cells:["M. Diallo","—","8 000 €","—",{b:"Bloqué",t:"red"}],
             detail:{title:"M. Diallo — ligne financière",tabs:[
               {name:"Ligne",blocks:[{type:"fields",data:[
                 ["Contrat","Diallo / TechnoSoft"],["Dossier OPCO","OPCO EP (rejeté)"],["Montant attendu","8 000 €"],["Montant accepté","—"],
                 ["Montant facturé","—"],["Montant encaissé","—"],["Montant bloqué","8 000 €"],["Statut",{b:"Bloqué",t:"red"}],["Motif de blocage","Rejet OPCO (pièce manquante)"]]}]},
               {name:"Blocage",blocks:[{type:"list",items:[{text:"Blocage suite au rejet OPCO du 18/06",meta:"",tone:"red"},{text:"Déblocage après correction + acceptation OPCO",meta:"action attendue",tone:"amber"}]},
                 {type:"rules",items:["Un montant bloqué doit avoir un motif.","Un paiement reçu doit être relié à une facture ou à une ligne financière."]}]},
             ]}},
            ["N. Lefèvre","TechnoSoft","9 200 €","15/07/2026",{b:"Facturé",t:"blue"}],
            ["K. Benali","Cabinet Léa","8 800 €","30/06/2026",{b:"Payé",t:"green"}],
            ["A. Moreau","GreenLog","7 500 €","10/08/2026",{b:"Prévisionnel",t:"gray"}],
          ]},
        {type:"list",title:"Retards & blocages",items:[
          {text:"M. Diallo — 8 000 € bloqués (rejet OPCO)",meta:"motif : pièce manquante",tone:"red"},
          {text:"Facture #2026-041 en retard (12 j)",meta:"BTP Sud",tone:"amber"}]},
        {type:"subheading",icon:"🧾",text:"Facturation"},
        {type:"table",title:"Factures",columns:["N° facture","Destinataire","Montant","Échéance","Statut"],
          rows:[
            ["2026-039","TechnoSoft","9 200 €","15/07/2026",{b:"Facturé",t:"blue"}],
            ["2026-040","Cabinet Léa","8 800 €","30/06/2026",{b:"Payé",t:"green"}],
            ["2026-041","BTP Sud","6 500 €","18/06/2026",{b:"En retard",t:"red"}],
          ]},
        {type:"rules",items:["Une facture ne doit pas être marquée émise sans montant et destinataire.","Un paiement reçu doit être relié à une facture ou à une ligne financière.","Un montant bloqué doit avoir un motif."]},
      ]
    },

    /* ============================================================ QUALITÉ */
    {
      id:"qualite", label:"Qualité", icon:"🏅", role:"Qualité",
      title:"Espace qualité & preuves",
      intro:"Centraliser les preuves Qualiopi et missions CFA ; suivre indicateurs, preuves manquantes, actions correctives et réclamations.",
      kpis:[
        {label:"Indicateurs couverts",value:"28 / 32",sub:"",tone:"green"},
        {label:"Preuves manquantes",value:"4",sub:"à risque",tone:"red"},
        {label:"Actions correctives",value:"3",sub:"ouvertes",tone:"blue"},
        {label:"Réclamations",value:"1",sub:"en cours",tone:"amber"},
      ],
      blocks:[
        {type:"subheading",icon:"🏅",text:"Qualité & preuves",note:"Les preuves se constituent au fil du parcours (un contrat signé, une présence validée… génèrent une preuve)."},
        {type:"chart",title:"Couverture des indicateurs Qualiopi",max:32,items:[
          {label:"Validés",value:28,tone:"green"},{label:"Manquants",value:4,tone:"red"}]},
        {type:"actions",items:["Créer une preuve","Rattacher (candidat, apprenant, contrat, entreprise, session)","Rattacher à un indicateur","Rattacher à une mission CFA","Statut","Signaler une preuve manquante","Créer une action corrective","Exporter un pack de preuves"]},
        {type:"tags",title:"Types de preuves",items:["Entretien candidat","Test de positionnement","Action de recherche entreprise","Contrat signé","Livret / suivi pédagogique","Présence / assiduité","Justificatif","Évaluation","Satisfaction","Réclamation","Action corrective","Accompagnement rupture","Preuve de service fait"]},
        {type:"statuses",title:"Statuts d'une preuve",items:["Validée","Refusée","Manquante"],current:"Manquante"},
        {type:"table",title:"Preuves par indicateur",columns:["Indicateur","Type de preuve","Dossier lié","Statut"],
          rows:[
            {cells:["Ind. 11 — Atteinte des objectifs","Évaluation","—",{b:"Manquante",t:"red"}],
             detail:{title:"Indicateur 11 — Atteinte des objectifs",tabs:[
               {name:"Indicateur",blocks:[{type:"fields",data:[["Indicateur","11 — Atteinte des objectifs"],["Mission CFA","Suivi pédagogique"],["Preuves attendues","Évaluations des apprenants"],["Statut",{b:"Manquante",t:"red"}]]}]},
               {name:"Preuves liées",blocks:[{type:"table",columns:["Preuve","Dossier","Statut"],rows:[
                 ["Évaluation S1","Groupe BTS SIO",{b:"Manquante",t:"red"}],
                 ["Bulletins semestriels","—",{b:"Manquante",t:"red"}]]}]},
               {name:"Action corrective",blocks:[{type:"list",items:[{text:"Mettre à jour les évaluations Ind. 11",meta:"resp. C. Petit · échéance 10/07",tone:"blue"}]},
                 {type:"rules",items:["Un indicateur ne peut pas être complet sans preuve associée.","Une action corrective doit avoir un responsable et une date limite."]}]},
             ]}},
            ["Ind. 4 — Adéquation","Test de positionnement","S. Garnier",{b:"Validée",t:"green"}],
            ["Ind. 9 — Accueil","Entretien candidat","K. Benali",{b:"Validée",t:"green"}],
            ["Ind. 22 — Recueil appréciations","Satisfaction","Groupe BTS",{b:"Manquante",t:"red"}],
          ]},
        {type:"list",title:"Actions correctives ouvertes",items:[
          {text:"Mettre à jour les évaluations (Ind. 11)",meta:"resp. C. Petit · échéance 10/07",tone:"blue"},
          {text:"Lancer l'enquête de satisfaction (Ind. 22)",meta:"resp. A. Roux · échéance 15/07",tone:"blue"},
          {text:"Traiter la réclamation #R-2026-03",meta:"resp. L. Faure · échéance 05/07",tone:"amber"}]},
        {type:"rules",items:["Un indicateur qualité ne peut pas être considéré comme complet sans preuve associée.","Une action corrective doit avoir un responsable et une date limite."]},
      ]
    },

    /* ================================================================= RH */
    {
      id:"rh", label:"RH", icon:"👥", role:"RH", vision:true,
      title:"Espace RH",
      intro:"Gestion des formateurs et du personnel (vision élargie au-delà du CDC v2). Hors V1 — présenté pour valider la cible avec l'équipe RH.",
      kpis:[
        {label:"Formateurs",value:"14",sub:"actifs",tone:"blue"},
        {label:"Personnel admin",value:"8",sub:"",tone:"blue"},
        {label:"Absences en cours",value:"2",sub:"congés",tone:"amber"},
        {label:"Entretiens à mener",value:"5",sub:"annuels",tone:"amber"},
      ],
      blocks:[
        {type:"subheading",icon:"👥",text:"Personnel & formateurs",note:"Module de vision : à détailler avec l'équipe RH (contrats, congés, compétences, planning, paie…)."},
        {type:"actions",items:["Créer une fiche salarié","Gérer les formateurs","Suivre les contrats RH","Congés / absences","Compétences & habilitations","Entretiens annuels","Lier formateur ↔ sessions"]},
        {type:"chart",title:"Répartition des effectifs",items:[
          {label:"Formateurs",value:14,tone:"blue"},{label:"Administratif",value:8,tone:"blue"},{label:"Encadrement",value:3,tone:"green"}]},
        {type:"table",title:"Personnel & formateurs",columns:["Nom","Rôle","Statut","Disponibilité"],
          rows:[
            ["C. Petit","Formateur SIO",{b:"Actif",t:"green"},"Temps plein"],
            ["A. Roux","Formateur Commerce",{b:"Actif",t:"green"},"Vacataire"],
            ["S. Martin","Référent OPCO",{b:"Congé",t:"amber"},"Retour 12/07"],
            ["L. Faure","Responsable péda.",{b:"Actif",t:"green"},"Temps plein"],
          ]},
        {type:"rules",items:["Module hors périmètre V1 : à cadrer avec l'équipe RH avant développement."]},
      ]
    },

    /* ========================================================== MARKETING */
    {
      id:"marketing", label:"Marketing", icon:"📣", role:"Marketing", vision:true,
      title:"Espace marketing",
      intro:"Acquisition de candidats, campagnes et sources de leads (vision élargie). Hors V1 — présenté pour valider la cible avec l'équipe marketing.",
      kpis:[
        {label:"Leads ce mois",value:"210",sub:"+18 %",tone:"green"},
        {label:"Conversion",value:"19 %",sub:"lead → candidat",tone:"blue"},
        {label:"Campagnes actives",value:"3",sub:"",tone:"blue"},
        {label:"Coût / lead",value:"6,40 €",sub:"moyenne",tone:"amber"},
      ],
      blocks:[
        {type:"subheading",icon:"📣",text:"Acquisition & campagnes",note:"Module de vision : à détailler avec l'équipe marketing (campagnes, formulaires, sources, conversion)."},
        {type:"actions",items:["Suivre les sources de leads","Créer une campagne","Mesurer la conversion","Relier un lead à un candidat","Coût par lead","Formulaires de captation"]},
        {type:"chart",title:"Leads par source",max:92,items:[
          {label:"Site web",value:92,tone:"blue"},{label:"Réseaux sociaux",value:55,tone:"blue"},
          {label:"Salons",value:48,tone:"amber"},{label:"Recommandation",value:15,tone:"green"}]},
        {type:"table",title:"Sources de leads",columns:["Source","Leads","Candidats","Conversion"],
          rows:[
            ["Site web","92","21","23 %"],["Salons","48","12","25 %"],
            ["Réseaux sociaux","55","6","11 %"],["Recommandation","15","8","53 %"],
          ]},
        {type:"rules",items:["Module hors périmètre V1 : à cadrer avec l'équipe marketing avant développement."]},
      ]
    },

    /* ===================================================== ADMINISTRATION */
    {
      id:"admin", label:"Administration", icon:"⚙️", role:"Administrateur",
      title:"Administration système",
      intro:"Gestion des utilisateurs, rôles et permissions ; journal des actions ; recherche transversale et exports.",
      kpis:[
        {label:"Utilisateurs",value:"23",sub:"actifs",tone:"blue"},
        {label:"Rôles",value:"10",sub:"configurés",tone:"blue"},
        {label:"Connexions (24 h)",value:"41",sub:"",tone:"green"},
        {label:"Actions journalisées",value:"1 284",sub:"ce mois",tone:"gray"},
      ],
      blocks:[
        {type:"subheading",icon:"🔐",text:"Utilisateurs, rôles & permissions",note:"Sécuriser l'accès aux données et actions de l'ERP."},
        {type:"actions",items:["Créer un utilisateur","Modifier","Désactiver (sans supprimer l'historique)","Attribuer un rôle","Modifier les permissions d'un rôle","Consulter les connexions","Consulter les actions sensibles"]},
        {type:"matrix",title:"Matrice des permissions (rôles × modules)",legend:"✓ accès complet · ◐ lecture seule · – aucun accès — selon les règles du CDC §20",
          columns:["Candidats","Entreprises","Contrats","OPCO","Finance","Pédagogie","Qualité","Admin"],
          rows:[
            {label:"Direction",values:["p","p","p","p","p","p","p","false"]},
            {label:"Commercial",values:["true","true","p","false","false","false","false","false"]},
            {label:"Admission",values:["true","p","p","false","false","false","p","false"]},
            {label:"Administratif",values:["p","p","true","true","p","false","false","false"]},
            {label:"Pédagogie",values:["p","false","p","false","false","true","p","false"]},
            {label:"Finance",values:["false","false","p","p","true","false","false","false"]},
            {label:"Qualité",values:["p","p","p","p","false","p","true","false"]},
            {label:"Formateur",values:["p","false","false","false","false","p","false","false"]},
            {label:"Administrateur",values:["true","true","true","true","true","true","true","true"]},
          ]},
        {type:"table",title:"Utilisateurs & rôles",columns:["Nom","Rôle","Statut","Dernière connexion"],
          rows:[
            ["Admin","Administrateur",{b:"Actif",t:"green"},"il y a 5 min"],
            ["S. Martin","Administratif",{b:"Actif",t:"green"},"il y a 1 h"],
            ["A. Roux","Commercial",{b:"Actif",t:"green"},"hier"],
            ["J. Durand","Finance",{b:"Désactivé",t:"gray"},"30/05/2026"],
          ]},
        {type:"rules",items:["Un utilisateur ne doit accéder qu'aux modules nécessaires à son rôle.","Un commercial ne doit pas modifier les données financières.","Un formateur ne doit pas accéder aux informations financières.","La finance ne doit pas modifier les évaluations pédagogiques.","Désactiver un compte ne supprime pas son historique."]},

        {type:"subheading",icon:"🕓",text:"Historique & journalisation",note:"Conserver une trace des actions importantes réalisées par les utilisateurs."},
        {type:"table",title:"Journal des actions",columns:["Utilisateur","Date & heure","Action","Objet","Ancienne → nouvelle valeur"],
          rows:[
            ["S. Martin","30/06 10:42","Changement statut OPCO","M. Diallo","Déposé → Rejeté"],
            ["Admin","29/06 16:10","Création utilisateur","C. Petit","— → Pédagogie"],
            ["A. Roux","29/06 09:33","Validation admission","S. Garnier","À vérifier → Validé"],
            ["C. Petit","28/06 14:05","Validation service fait","Groupe BTS SIO","À valider → Validée"],
          ]},

        {type:"subheading",icon:"🔎",text:"Recherche & exports",note:"Retrouver rapidement l'information et exporter les données utiles."},
        {type:"actions",items:["Rechercher : candidat, entreprise, contrat, OPCO, document, facture, tâche, preuve","Filtrer : statut, formation, responsable, période, entreprise, type, priorité, anomalie"]},
        {type:"tags",title:"Exports disponibles",items:["Liste candidats","Liste entreprises","Liste besoins","Liste contrats","Liste dossiers OPCO","Documents manquants","Liste des absences","Services faits","Tableau financier","Pack de preuves qualité"]},
        {type:"rules",items:["Un export sensible doit être réservé aux utilisateurs autorisés.","Un export conserve la date de génération et l'utilisateur qui l'a généré."]},
      ]
    }
  ]
};
