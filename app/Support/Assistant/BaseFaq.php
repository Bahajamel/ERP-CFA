<?php

namespace App\Support\Assistant;

use Illuminate\Support\Str;

/**
 * Base de connaissance de l'assistant d'aide (« Demander à l'IA »).
 *
 * 100 % local : aucune donnée ne sort de l'ERP, aucune API externe, aucun coût.
 * Chaque entrée décrit un cas d'usage réel du logiciel, avec des mots-clés
 * (synonymes inclus) pour la recherche et un lien direct vers la bonne page.
 * La recherche est insensible à la casse et aux accents, et tolère les fautes
 * de conjugaison/pluriel courantes.
 */
class BaseFaq
{
    /** Mots vides ignorés lors de la recherche (ne portent pas de sens métier). */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'a', 'au', 'aux', 'et', 'ou', 'où',
        'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'mon', 'ma', 'mes', 'se', 'ce',
        'cet', 'cette', 'ces', 'est', 'sont', 'dans', 'pour', 'sur', 'avec', 'par', 'que', 'qui',
        'quoi', 'quel', 'quelle', 'quels', 'quelles', 'comment', 'pourquoi', 'faire', 'fait',
        'veux', 'peux', 'puis', 'dois', 'y', 'en', 'sa', 'son', 'ses', 'the', 'to', 'me', 'te',
    ];

    /**
     * Les entrées de la FAQ.
     *
     * @return array<int, array{categorie:string, question:string, mots:array<int,string>, reponse:string, lien?:array{route:string, label:string, permission?:string}}>
     */
    public static function entrees(): array
    {
        return [
            // ─── Prise en main ──────────────────────────────────────────────
            [
                'categorie' => 'Prise en main',
                'question' => 'Par où commencer ?',
                'mots' => ['commencer', 'debuter', 'debut', 'demarrer', 'demarrage', 'guide', 'parcours', 'etapes', 'perdu', 'aide'],
                'reponse' => "Suivez le parcours d'un apprenant, une étape = un module : 1) Candidat, 2) Admission, 3) Entreprise, 4) Besoin, 5) Matching, 6) Contrat, 7) Dossier OPCO. Le « Guide de démarrage » en haut de l'accueil vous mène directement à chaque étape.",
                'lien' => ['route' => 'filament.admin.pages.dashboard', 'label' => "Ouvrir l'accueil"],
            ],
            [
                'categorie' => 'Prise en main',
                'question' => 'Comment rechercher rapidement une information ?',
                'mots' => ['rechercher', 'recherche', 'chercher', 'trouver', 'barre', 'globale', 'raccourci'],
                'reponse' => "Utilisez la barre de recherche globale en haut de l'écran : tapez un nom de candidat, d'entreprise ou un mot-clé, les résultats s'affichent instantanément avec un accès direct à la fiche.",
            ],

            // ─── Candidats ──────────────────────────────────────────────────
            [
                'categorie' => 'Candidats',
                'question' => 'Comment ajouter un apprenant / candidat ?',
                'mots' => ['ajouter', 'creer', 'nouveau', 'nouvelle', 'candidat', 'apprenant', 'apprenti', 'inscrire', 'inscription', 'personne'],
                'reponse' => "Ouvrez le module « Candidats » puis cliquez sur « + Nouveau candidat ». Renseignez l'identité, le contact et la formation visée. Le candidat devient ensuite éligible à l'admission.",
                'lien' => ['route' => 'filament.admin.resources.candidates.create', 'label' => 'Ajouter un candidat', 'permission' => 'access_candidates'],
            ],
            [
                'categorie' => 'Candidats',
                'question' => 'Comment modifier ou consulter la fiche d\'un candidat ?',
                'mots' => ['modifier', 'consulter', 'voir', 'fiche', 'candidat', 'apprenant', 'editer', 'coordonnees', 'contact'],
                'reponse' => 'Dans « Candidats », cliquez sur la ligne de la personne pour ouvrir sa fiche. Vous y trouvez son identité, ses documents, sa formation visée et son suivi (admission, contrat, scolarité).',
                'lien' => ['route' => 'filament.admin.resources.candidates.index', 'label' => 'Voir les candidats', 'permission' => 'access_candidates'],
            ],
            [
                'categorie' => 'Candidats',
                'question' => 'J\'ai supprimé un candidat par erreur, comment le récupérer ?',
                'mots' => ['supprime', 'supprimer', 'restaurer', 'recuperer', 'corbeille', 'erreur', 'annuler', 'efface'],
                'reponse' => 'Les candidats supprimés partent à la « Corbeille » (suppression douce). Ouvrez la Corbeille pour restaurer une fiche ou la supprimer définitivement.',
                'lien' => ['route' => 'filament.admin.resources.candidates.index', 'label' => 'Module Candidats', 'permission' => 'access_candidates'],
            ],
            [
                'categorie' => 'Candidats',
                'question' => 'Comment planifier un entretien avec un candidat ?',
                'mots' => ['entretien', 'rendez-vous', 'rdv', 'planifier', 'rencontre', 'candidat'],
                'reponse' => 'Le module « Entretiens » recense les rendez-vous avec les candidats. Créez un entretien, choisissez le candidat et la date : il apparaîtra dans le suivi de sa fiche.',
                'lien' => ['route' => 'filament.admin.resources.entretiens.index', 'label' => 'Voir les entretiens', 'permission' => 'access_candidates'],
            ],

            // ─── Admission ──────────────────────────────────────────────────
            [
                'categorie' => 'Admission',
                'question' => 'Comment valider l\'admission d\'un candidat ?',
                'mots' => ['admission', 'valider', 'admettre', 'entree', 'dossier', 'pieces', 'obligatoires', 'accepter'],
                'reponse' => "Dans « Admission », ouvrez le dossier du candidat : l'ERP liste les pièces obligatoires (pièce d'identité, CV, diplômes, test de positionnement…). Une fois les pièces présentes, validez l'entrée au CFA.",
                'lien' => ['route' => 'filament.admin.resources.admissions.index', 'label' => 'Ouvrir les admissions', 'permission' => 'access_admissions'],
            ],
            [
                'categorie' => 'Admission',
                'question' => 'Quelles pièces sont obligatoires pour un dossier d\'admission ?',
                'mots' => ['pieces', 'documents', 'obligatoires', 'manquantes', 'justificatifs', 'identite', 'cv', 'diplome', 'positionnement'],
                'reponse' => "Les pièces attendues à l'admission sont : pièce d'identité, CV du candidat, diplômes/bulletins et test de positionnement. Le dossier d'admission signale en rouge celles qui manquent encore.",
                'lien' => ['route' => 'filament.admin.resources.admissions.index', 'label' => 'Ouvrir les admissions', 'permission' => 'access_admissions'],
            ],

            // ─── Entreprises & besoins ──────────────────────────────────────
            [
                'categorie' => 'Entreprises',
                'question' => 'Comment ajouter une entreprise partenaire ?',
                'mots' => ['entreprise', 'societe', 'employeur', 'ajouter', 'creer', 'partenaire', 'nouvelle'],
                'reponse' => "Ouvrez « Entreprises » puis « + Nouvelle entreprise ». Renseignez la raison sociale, le contact et les informations légales. L'entreprise pourra ensuite exprimer des besoins et accueillir des apprentis.",
                'lien' => ['route' => 'filament.admin.resources.companies.create', 'label' => 'Ajouter une entreprise', 'permission' => 'access_companies'],
            ],
            [
                'categorie' => 'Besoins',
                'question' => 'Comment créer un besoin (poste en alternance) ?',
                'mots' => ['besoin', 'poste', 'offre', 'recrutement', 'alternance', 'creer', 'ajouter', 'pourvoir'],
                'reponse' => "Dans « Besoins », créez le poste que l'entreprise veut pourvoir (intitulé, entreprise, profil recherché). Ce besoin pourra ensuite être rapproché d'un candidat via le Matching.",
                'lien' => ['route' => 'filament.admin.resources.needs.create', 'label' => 'Créer un besoin', 'permission' => 'access_needs'],
            ],
            [
                'categorie' => 'Matching',
                'question' => 'Comment rapprocher un candidat d\'un besoin (matching) ?',
                'mots' => ['matching', 'rapprocher', 'proposition', 'associer', 'candidat', 'besoin', 'mettre', 'relation', 'placer'],
                'reponse' => "Le module « Matching » rapproche un candidat du bon besoin d'entreprise. Créez une proposition en choisissant le candidat et le besoin ; suivez ensuite son avancement jusqu'à la contractualisation.",
                'lien' => ['route' => 'filament.admin.resources.matchings.create', 'label' => 'Nouvelle proposition', 'permission' => 'access_matching'],
            ],

            // ─── Contrats & signature ───────────────────────────────────────
            [
                'categorie' => 'Contrats',
                'question' => 'Comment établir un contrat d\'apprentissage / CERFA ?',
                'mots' => ['contrat', 'cerfa', 'apprentissage', 'etablir', 'creer', 'convention', 'generer', 'contractualisation'],
                'reponse' => "Dans « Contrats », créez le contrat d'apprentissage : l'ERP génère automatiquement le CERFA et la convention à partir des données du candidat et de l'entreprise. Vous pouvez ensuite l'envoyer à la signature.",
                'lien' => ['route' => 'filament.admin.resources.contracts.create', 'label' => 'Créer un contrat', 'permission' => 'access_contracts'],
            ],
            [
                'categorie' => 'Contrats',
                'question' => 'Comment faire signer un contrat électroniquement ?',
                'mots' => ['signer', 'signature', 'electronique', 'envoyer', 'signataire', 'valider', 'contrat', 'cerfa'],
                'reponse' => "Depuis la fiche du contrat, lancez la signature électronique : chaque signataire (apprenti, entreprise, CFA) reçoit le document à signer. L'avancement des signatures est suivi directement sur le contrat.",
                'lien' => ['route' => 'filament.admin.resources.contracts.index', 'label' => 'Voir les contrats', 'permission' => 'access_contracts'],
            ],
            [
                'categorie' => 'Contrats',
                'question' => 'Comment déclarer une rupture de contrat ?',
                'mots' => ['rupture', 'rompre', 'arreter', 'abandon', 'fin', 'contrat', 'declarer', 'demission'],
                'reponse' => "Le module « Ruptures » sert à déclarer et suivre les ruptures de contrat d'apprentissage (motif, date). Créez une rupture rattachée au contrat concerné.",
                'lien' => ['route' => 'filament.admin.resources.ruptures.create', 'label' => 'Déclarer une rupture', 'permission' => 'access_ruptures'],
            ],

            // ─── OPCO & finance ─────────────────────────────────────────────
            [
                'categorie' => 'OPCO',
                'question' => 'Comment créer et suivre un dossier OPCO ?',
                'mots' => ['opco', 'dossier', 'financement', 'financer', 'prise', 'charge', 'creer', 'suivre', 'operateur'],
                'reponse' => "Dans « Dossiers OPCO », créez le dossier de financement d'un contrat, puis suivez son état (déposé, accepté) et les versements. C'est l'étape qui fait financer le contrat par l'OPCO.",
                'lien' => ['route' => 'filament.admin.resources.opco-files.create', 'label' => 'Nouveau dossier OPCO', 'permission' => 'access_opco'],
            ],
            [
                'categorie' => 'Finance',
                'question' => 'Où voir les versements et le suivi financier ?',
                'mots' => ['finance', 'versement', 'versements', 'argent', 'paiement', 'chiffre', 'tableau', 'bord', 'financier', 'recettes', 'montant'],
                'reponse' => 'La page « Finance » agrège automatiquement les montants des dossiers OPCO et des contrats : recettes attendues, versements reçus, restes à percevoir. Tout est synchronisé en continu, sans action manuelle. Cliquez sur un indicateur pour aller au détail.',
                'lien' => ['route' => 'filament.admin.pages.finance', 'label' => 'Ouvrir Finance', 'permission' => 'access_finance'],
            ],

            // ─── Formations & classes ───────────────────────────────────────
            [
                'categorie' => 'Formations',
                'question' => 'Comment créer une formation et ses matières ?',
                'mots' => ['formation', 'diplome', 'cursus', 'matiere', 'matieres', 'programme', 'creer', 'ajouter'],
                'reponse' => "Dans « Formations », créez la formation (libellé, niveau) et saisissez la liste de ses matières. Ces matières alimentent ensuite les classes, l'emploi du temps et le cahier de notes.",
                'lien' => ['route' => 'filament.admin.resources.formations.create', 'label' => 'Créer une formation', 'permission' => 'access_formations'],
            ],
            [
                'categorie' => 'Scolarité',
                'question' => 'Comment créer une classe / promotion ?',
                'mots' => ['classe', 'promotion', 'cohorte', 'groupe', 'creer', 'ajouter', 'annee', 'inscrire', 'apprentis'],
                'reponse' => 'Dans « Classes », créez une promotion (formation + année scolaire) puis composez-la en y rattachant les apprentis. Un apprenant ne peut suivre que des classes de sa formation et de son niveau.',
                'lien' => ['route' => 'filament.admin.resources.promotions.create', 'label' => 'Créer une classe', 'permission' => 'access_formations'],
            ],
            [
                'categorie' => 'Scolarité',
                'question' => 'Comment gérer l\'emploi du temps ?',
                'mots' => ['emploi', 'temps', 'planning', 'horaire', 'seance', 'creneau', 'agenda', 'calendrier', 'cours'],
                'reponse' => "La page « Emploi du temps » affiche le planning hebdomadaire des classes. Vous y organisez les séances (créneaux de cours) qui serviront aussi à l'émargement.",
                'lien' => ['route' => 'filament.admin.pages.emploi-du-temps', 'label' => "Ouvrir l'emploi du temps", 'permission' => 'access_attendance'],
            ],
            [
                'categorie' => 'Scolarité',
                'question' => 'Comment faire l\'émargement / suivre l\'assiduité ?',
                'mots' => ['emargement', 'emarger', 'presence', 'absence', 'assiduite', 'appel', 'presences', 'absents', 'justificatif'],
                'reponse' => "La page « Assiduité » permet de pointer les présences/absences par séance et de suivre l'assiduité des apprentis. Les justificatifs d'absence peuvent y être rattachés.",
                'lien' => ['route' => 'filament.admin.pages.assiduite', 'label' => "Ouvrir l'assiduité", 'permission' => 'access_attendance'],
            ],

            // ─── Notes & bulletins ──────────────────────────────────────────
            [
                'categorie' => 'Notes',
                'question' => 'Comment saisir des notes / le cahier de notes ?',
                'mots' => ['note', 'notes', 'saisir', 'saisie', 'evaluation', 'epreuve', 'controle', 'examen', 'devoir', 'cahier', 'noter', 'moyenne'],
                'reponse' => 'Ouvrez « Notes », choisissez une classe puis une matière : le cahier affiche les apprentis et leurs notes. Le bouton « Nouvelle épreuve » permet de saisir une note pour toute la classe en une fois (type, barème, coefficient).',
                'lien' => ['route' => 'filament.admin.pages.notes', 'label' => 'Ouvrir le cahier de notes', 'permission' => 'access_attendance'],
            ],
            [
                'categorie' => 'Notes',
                'question' => 'Comment importer la copie d\'examen d\'un apprenant comme preuve ?',
                'mots' => ['copie', 'preuve', 'examen', 'importer', 'feuille', 'justifier', 'piece', 'joindre', 'scan', 'controle'],
                'reponse' => "Dans le cahier de notes, la colonne « Examen (preuve) » permet d'importer la copie de chaque apprenant. Au moment de l'import, choisissez le type (Contrôle ou Examen) : la note du même type devient alors cliquable et ouvre la copie correspondante.",
                'lien' => ['route' => 'filament.admin.pages.notes', 'label' => 'Ouvrir le cahier de notes', 'permission' => 'access_attendance'],
            ],
            [
                'categorie' => 'Notes',
                'question' => 'Pourquoi une note n\'est pas cliquable ?',
                'mots' => ['cliquable', 'cliquer', 'note', 'lien', 'trombone', 'copie', 'ouvre', 'preuve', 'type'],
                'reponse' => "Une note n'est cliquable que si une copie du MÊME type existe : une note de type « Contrôle » n'ouvre qu'une copie « Contrôle », une note « Examen » qu'une copie « Examen ». Pour la rendre cliquable, importez une copie du type correspondant.",
                'lien' => ['route' => 'filament.admin.pages.notes', 'label' => 'Ouvrir le cahier de notes', 'permission' => 'access_attendance'],
            ],
            [
                'categorie' => 'Notes',
                'question' => 'Comment générer un bulletin de notes ?',
                'mots' => ['bulletin', 'bulletins', 'generer', 'editer', 'pdf', 'releve', 'moyennes', 'apprenant'],
                'reponse' => "Depuis la fiche d'un apprenant (pop-up scolarité), vous pouvez générer son bulletin en PDF : il est calculé à partir des notes saisies et archivé automatiquement dans sa GED (documents). Un bulletin externe peut aussi y être importé.",
                'lien' => ['route' => 'filament.admin.resources.candidates.index', 'label' => 'Voir les apprenants', 'permission' => 'access_candidates'],
            ],

            // ─── Documents, tâches, qualité ─────────────────────────────────
            [
                'categorie' => 'Documents',
                'question' => 'Où retrouver les documents (GED) ?',
                'mots' => ['document', 'documents', 'ged', 'fichier', 'fichiers', 'piece', 'archive', 'telecharger', 'stockage'],
                'reponse' => "Le module « Documents » centralise la GED : contrats, CERFA, bulletins, justificatifs, etc. Chaque document est aussi accessible depuis la fiche de l'apprenant, de l'entreprise ou du contrat concerné.",
                'lien' => ['route' => 'filament.admin.resources.documents.index', 'label' => 'Ouvrir les documents', 'permission' => 'access_documents'],
            ],
            [
                'categorie' => 'Tâches',
                'question' => 'Comment créer une tâche ou un rappel ?',
                'mots' => ['tache', 'taches', 'rappel', 'todo', 'suivi', 'relance', 'creer', 'action', 'echeance'],
                'reponse' => 'Le module « Tâches » vous aide à suivre vos actions (relances, échéances). Créez une tâche, fixez une échéance et suivez son avancement.',
                'lien' => ['route' => 'filament.admin.resources.tasks.create', 'label' => 'Créer une tâche', 'permission' => 'access_tasks'],
            ],
            [
                'categorie' => 'Qualité',
                'question' => 'Où suivre les indicateurs Qualiopi ?',
                'mots' => ['qualiopi', 'qualite', 'indicateur', 'indicateurs', 'conformite', 'audit', 'certification'],
                'reponse' => "Le module « Qualiopi » recense les indicateurs de la certification qualité et leur état de conformité, pour préparer sereinement l'audit.",
                'lien' => ['route' => 'filament.admin.resources.qualiopi-indicators.index', 'label' => 'Voir les indicateurs', 'permission' => 'access_quality'],
            ],

            // ─── Tableaux personnalisés (façon Monday) ──────────────────────
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment créer un tableau personnalisé (façon Monday) ?',
                'mots' => ['tableau', 'tableaux', 'personnalise', 'personnalises', 'board', 'monday', 'creer', 'ajouter', 'nouveau', 'custom', 'suivi', 'vivier', 'partenaires', 'prospects'],
                'reponse' => "Chaque CFA peut créer ses propres tableaux, par module. Depuis « Base Candidats » (ou Entreprises / Offres), cliquez sur l'onglet « + Nouveau tableau » du sélecteur de tables en haut : donnez un nom, définissez les colonnes, puis saisissez vos lignes. Vous pouvez aussi passer par Administration → « Tableaux personnalisés ». Réservé aux rôles Commercial, Direction et Administrateur.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.create', 'label' => 'Créer un tableau', 'permission' => 'custom_tables.create'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment passer d\'un tableau à un autre ?',
                'mots' => ['basculer', 'changer', 'passer', 'naviguer', 'selecteur', 'onglet', 'onglets', 'board', 'boards', 'plusieurs', 'tableau', 'tableaux', 'module'],
                'reponse' => "En haut de la Base Candidats (et des Entreprises / Offres), une barre d'onglets « boards » liste le board principal du module et tous vos tableaux personnalisés de ce module. Cliquez sur un onglet pour basculer d'un tableau à l'autre ; l'onglet courant est surligné.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment ajouter une ligne ou une colonne à un tableau ?',
                'mots' => ['ligne', 'lignes', 'colonne', 'colonnes', 'ajouter', 'saisir', 'remplir', 'champ', 'champs', 'tableau', 'element'],
                'reponse' => "Ouvrez le tableau (clic sur son nom) : le board affiche ses lignes. Utilisez « Ajouter une ligne » et « Ajouter une colonne » dans la barre du tableau. Le bouton « Configurer » ouvre l'écran complet (nom, icône, couleur, module, colonnes).",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Quels types de colonnes puis-je créer et comment marchent les listes déroulantes ?',
                'mots' => ['type', 'types', 'colonne', 'liste', 'deroulante', 'statut', 'option', 'options', 'choix', 'texte', 'nombre', 'date', 'utilisateur', 'select', 'booleen', 'fichier', 'piece', 'jointe', 'cv', 'relation', 'lien', 'fiche'],
                'reponse' => 'Types disponibles : texte court, texte long, nombre, date, heure, oui/non, e-mail, téléphone, lien (URL), montant, pourcentage, liste déroulante, multi-sélection, statut (coloré), utilisateur assigné, fichier (pièce jointe) et relation (lien vers une fiche). Pour une colonne « Liste » ou « Statut », au moment de saisir une ligne vous choisissez une option existante ou en ajoutez une nouvelle à la volée : elle est mémorisée pour tout le monde. Une colonne « Fichier » permet de joindre un document à chaque ligne (et de recevoir un CV via le lien public). Une colonne « Relation » relie chaque ligne à une fiche de l\'ERP (candidat, entreprise, offre ou contrat) — non exposée au public. Les personnes qui remplissent le lien public ne reçoivent que les options déjà choisies.',
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment partager un lien pour qu\'une personne remplisse un tableau (candidature / entreprise) ?',
                'mots' => ['lien', 'candidature', 'entreprise', 'public', 'formulaire', 'partager', 'externe', 'jeton', 'deposer', 'remplir', 'tableau'],
                'reponse' => "Chaque tableau a son propre lien public. Ouvrez le board, cliquez sur « Lien de candidature » (ou « Lien entreprise » selon le module) : partagez l'URL. La personne remplit un formulaire bâti sur les colonnes du tableau, sans accès à l'ERP, et une ligne est créée dans ce tableau. Le formulaire reprend la charte du module.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Qui voit mes tableaux et comment inviter un collègue ?',
                'mots' => ['partager', 'partage', 'inviter', 'invitation', 'acces', 'prive', 'collegue', 'commercial', 'lecture', 'modification', 'droit', 'droits', 'voir', 'modifier'],
                'reponse' => "Chaque tableau est privé : seul son créateur y accède (la Direction et les Administrateurs voient tout, pour la supervision). Pour l'ouvrir à un collègue, cliquez sur « Partager » sur le board, ajoutez la personne et choisissez son niveau : « Lecture » (consulter) ou « Modification » (éditer lignes et colonnes). L'invité est prévenu par une notification. Retirez-le de la liste pour révoquer son accès.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment voir un tableau en Kanban et enregistrer une vue ?',
                'mots' => ['kanban', 'pipeline', 'carte', 'cartes', 'glisser', 'deposer', 'statut', 'vue', 'vues', 'enregistrer', 'filtre', 'tri', 'colonnes'],
                'reponse' => "Si le tableau a une colonne « Statut » (ou « Liste »), le bouton « Vue Kanban » l'affiche en colonnes : glissez une carte d'une colonne à l'autre pour changer son statut. Le menu « Vues » permet d'enregistrer un préréglage (filtres + tri) et d'en définir un par défaut.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],
            [
                'categorie' => 'Tableaux personnalisés',
                'question' => 'Comment archiver ou supprimer un tableau personnalisé ?',
                'mots' => ['supprimer', 'archiver', 'retirer', 'desactiver', 'corbeille', 'tableau', 'effacer'],
                'reponse' => "Pour retirer temporairement un tableau, décochez « Tableau actif » dans sa configuration (archivage réversible). Pour le supprimer, utilisez « Supprimer le tableau » sur le board : il part en corbeille (récupérable). La suppression est réservée à l'Administrateur.",
                'lien' => ['route' => 'filament.admin.resources.custom-tables.index', 'label' => 'Voir les tableaux', 'permission' => 'custom_tables.view'],
            ],

            // ─── Utilisateurs & compte (admin) ──────────────────────────────
            [
                'categorie' => 'Utilisateurs',
                'question' => 'Comment créer un utilisateur ou gérer les rôles ?',
                'mots' => ['utilisateur', 'utilisateurs', 'compte', 'role', 'roles', 'permission', 'permissions', 'acces', 'droits', 'creer', 'ajouter'],
                'reponse' => "Le module « Utilisateurs » (réservé à l'administrateur) permet de créer les comptes et d'attribuer un rôle métier (Direction, Finance, Administratif…). Chaque rôle ouvre l'accès aux seuls modules qui le concernent.",
                'lien' => ['route' => 'filament.admin.resources.users.index', 'label' => 'Gérer les utilisateurs', 'permission' => 'access_users'],
            ],
            [
                'categorie' => 'Utilisateurs',
                'question' => 'Comment changer un mot de passe ?',
                'mots' => ['mot', 'passe', 'password', 'oublie', 'reinitialiser', 'changer', 'modifier', 'connexion', 'identifiant'],
                'reponse' => "Pour des raisons de sécurité, seul l'administrateur peut modifier un mot de passe. Il le fait depuis le module « Utilisateurs » en éditant le compte concerné. Contactez-le si vous ne parvenez plus à vous connecter.",
                'lien' => ['route' => 'filament.admin.resources.users.index', 'label' => 'Gérer les utilisateurs', 'permission' => 'access_users'],
            ],
            [
                'categorie' => 'Paramètres',
                'question' => 'Où configurer les informations du CFA ?',
                'mots' => ['parametres', 'configuration', 'cfa', 'reglage', 'reglages', 'coordonnees', 'logo', 'entete', 'organisme'],
                'reponse' => "La page « Paramètres CFA » regroupe les informations de l'organisme (identité, coordonnées) utilisées notamment dans les documents générés (CERFA, conventions, bulletins).",
                'lien' => ['route' => 'filament.admin.pages.parametres-cfa', 'label' => 'Ouvrir les paramètres'],
            ],
        ];
    }

    /**
     * Quelques questions de démarrage proposées à l'ouverture de l'assistant.
     *
     * @return array<int, string>
     */
    public static function suggestions(): array
    {
        return [
            'Comment ajouter un apprenant ?',
            'Comment établir un contrat / CERFA ?',
            'Comment saisir des notes ?',
            'Comment créer un tableau personnalisé ?',
            'Par où commencer ?',
        ];
    }

    /**
     * Recherche les entrées les plus pertinentes pour un message.
     *
     * @return array<int, array<string, mixed>> Entrées triées par pertinence décroissante (score > 0).
     */
    public static function rechercher(string $message, int $limite = 3): array
    {
        $tokens = self::tokeniser($message);

        if ($tokens === []) {
            return [];
        }

        $resultats = [];

        foreach (self::entrees() as $entree) {
            $score = self::scorer($entree, $tokens);

            if ($score > 0) {
                $resultats[] = ['score' => $score] + $entree;
            }
        }

        usort($resultats, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($resultats, 0, $limite);
    }

    /** Score de pertinence d'une entrée face aux mots de la question. */
    private static function scorer(array $entree, array $tokens): int
    {
        $motsCles = array_map([self::class, 'normaliser'], $entree['mots']);
        $motsTitre = self::tokeniser($entree['question']);
        $score = 0;

        foreach ($tokens as $token) {
            foreach ($motsCles as $cle) {
                if ($token === $cle) {
                    $score += 3;
                } elseif (self::proche($token, $cle)) {
                    $score += 2;
                }
            }

            if (in_array($token, $motsTitre, true)) {
                $score += 1;
            }
        }

        return $score;
    }

    /** Deux mots « proches » : l'un préfixe l'autre (tolère pluriels/conjugaisons). */
    private static function proche(string $a, string $b): bool
    {
        if (mb_strlen($a) < 4 || mb_strlen($b) < 4) {
            return false;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * Découpe un texte en mots significatifs, normalisés et hors mots vides.
     *
     * @return array<int, string>
     */
    private static function tokeniser(string $texte): array
    {
        $mots = preg_split('/[^a-z0-9]+/', self::normaliser($texte), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $mots,
            fn (string $mot): bool => mb_strlen($mot) >= 2 && ! in_array($mot, self::STOPWORDS, true),
        ));
    }

    /** Minuscule + suppression des accents (recherche insensible casse/accents). */
    private static function normaliser(string $texte): string
    {
        return Str::lower(Str::ascii($texte));
    }
}
