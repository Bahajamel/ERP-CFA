<?php

namespace App\Qualiopi;

use App\Models\QualiopiIndicator;

/**
 * Référentiel National Qualité (Qualiopi) : 7 critères / 32 indicateurs.
 *
 * Le texte des indicateurs est national (identique pour tous), mais l'ÉTAT de
 * conformité (statut, responsable, preuves) est propre à chaque CFA. Depuis le
 * cloisonnement multi-tenant, chaque CFA possède donc ses 32 lignes.
 *
 * `provisionner()` crée/actualise ces 32 lignes pour un CFA donné : appelé au
 * seeding, à l'ouverture d'un essai et à la création manuelle d'un CFA. Idempotent
 * — il n'écrase jamais l'état de conformité déjà saisi (seuls les libellés de
 * référence sont rafraîchis).
 */
class ReferentielQualiopi
{
    /**
     * Provisionne les 32 indicateurs pour une organisation.
     * Sans global scope : fonctionne hors contexte tenant (CLI, migration).
     */
    public static function provisionner(int $organisationId): void
    {
        foreach (self::INDICATEURS as [$numero, $critere, $specifiqueCfa, $libelle]) {
            QualiopiIndicator::withoutGlobalScopes()->updateOrCreate(
                ['organisation_id' => $organisationId, 'numero' => $numero],
                ['critere' => $critere, 'specifique_cfa' => $specifiqueCfa, 'libelle' => $libelle],
            );
        }
    }

    /** @var list<array{0:int,1:int,2:bool,3:string}> [numéro, critère, spécifique CFA, libellé] */
    public const INDICATEURS = [
        // Critère 1 — Information du public
        [1, 1, false, 'Le prestataire diffuse une information accessible au public, détaillée et vérifiable sur les prestations proposées : prérequis, objectifs, durée, modalités et délais d\'accès, tarifs, contacts, méthodes mobilisées et modalités d\'évaluation, accessibilité aux personnes handicapées.'],
        [2, 1, false, 'Le prestataire diffuse des indicateurs de résultats adaptés à la nature des prestations mises en œuvre et des publics accueillis.'],
        [3, 1, false, 'Lorsque le prestataire met en œuvre des prestations conduisant à une certification professionnelle, il informe sur les taux d\'obtention des certifications préparées, les possibilités de valider un/ou des blocs de compétences, ainsi que sur les équivalences, passerelles, suites de parcours et les débouchés.'],

        // Critère 2 — Objectifs et adaptation lors de la conception
        [4, 2, false, 'Le prestataire analyse le besoin du bénéficiaire en lien avec l\'entreprise et/ou le financeur concerné(s).'],
        [5, 2, false, 'Le prestataire définit les objectifs opérationnels et évaluables de la prestation.'],
        [6, 2, false, 'Le prestataire établit les contenus et les modalités de mise en œuvre de la prestation, adaptés aux objectifs définis et aux publics bénéficiaires.'],
        [7, 2, false, 'Lorsque le prestataire met en œuvre des prestations conduisant à une certification professionnelle, il s\'assure de l\'adéquation du ou des contenus de la prestation aux exigences de la certification visée.'],
        [8, 2, false, 'Le prestataire détermine les procédures de positionnement et d\'évaluation des acquis à l\'entrée de la prestation.'],

        // Critère 3 — Accueil, accompagnement, suivi et évaluation
        [9, 3, false, 'Le prestataire informe les publics bénéficiaires des conditions de déroulement de la prestation.'],
        [10, 3, false, 'Le prestataire met en œuvre et adapte la prestation, l\'accompagnement et le suivi aux publics bénéficiaires.'],
        [11, 3, false, 'Le prestataire évalue l\'atteinte par les publics bénéficiaires des objectifs de la prestation.'],
        [12, 3, false, 'Le prestataire décrit et met en œuvre les mesures pour favoriser l\'engagement des bénéficiaires et prévenir les ruptures de parcours.'],
        [13, 3, true, 'Pour les formations en alternance, le prestataire, en lien avec l\'entreprise, anticipe avec l\'apprenant les missions confiées, à court, moyen et long terme, et assure la coordination et la progressivité des apprentissages réalisés en centre de formation et en entreprise.'],
        [14, 3, true, 'Le prestataire met en œuvre un accompagnement socio-professionnel, éducatif et relatif à l\'exercice de la citoyenneté.'],
        [15, 3, true, 'Le prestataire informe les apprentis de leurs droits et devoirs en tant qu\'apprentis et salariés ainsi que des règles applicables en matière de santé et de sécurité en milieu professionnel.'],
        [16, 3, false, 'Lorsque le prestataire met en œuvre des formations conduisant à une certification professionnelle, il s\'assure que les conditions de présentation des bénéficiaires à la certification respectent les exigences formelles de l\'autorité de certification.'],

        // Critère 4 — Moyens pédagogiques, techniques et d'encadrement
        [17, 4, false, 'Le prestataire met à disposition ou s\'assure de la mise à disposition des moyens humains et techniques adaptés et d\'un environnement approprié (conditions, locaux, équipements, plateaux techniques…).'],
        [18, 4, false, 'Le prestataire mobilise et coordonne les différents intervenants internes et/ou externes (pédagogiques, administratifs, logistiques, commerciaux…).'],
        [19, 4, false, 'Le prestataire met à disposition du bénéficiaire des ressources pédagogiques et permet à celui-ci de se les approprier.'],
        [20, 4, true, 'Le prestataire dispose d\'un personnel dédié à l\'appui à la mobilité nationale et internationale, d\'un référent handicap et d\'un conseil de perfectionnement.'],

        // Critère 5 — Qualification et compétences des personnels
        [21, 5, false, 'Le prestataire détermine, mobilise et évalue les compétences des différents intervenants internes et/ou externes, adaptées aux prestations.'],
        [22, 5, false, 'Le prestataire entretient et développe les compétences de ses salariés, adaptées aux prestations qu\'il délivre.'],

        // Critère 6 — Inscription dans son environnement professionnel
        [23, 6, false, 'Le prestataire réalise une veille légale et réglementaire sur le champ de la formation professionnelle et en exploite les enseignements.'],
        [24, 6, false, 'Le prestataire réalise une veille sur les évolutions des compétences, des métiers et des emplois dans ses secteurs d\'intervention et en exploite les enseignements.'],
        [25, 6, false, 'Le prestataire réalise une veille sur les innovations pédagogiques et technologiques permettant une évolution de ses prestations et en exploite les enseignements.'],
        [26, 6, false, 'Le prestataire mobilise les expertises, outils et réseaux nécessaires pour accueillir, accompagner/former ou orienter les publics en situation de handicap.'],
        [27, 6, false, 'Lorsque le prestataire fait appel à la sous-traitance ou au portage salarial, il s\'assure du respect de la conformité au présent référentiel.'],
        [28, 6, true, 'Lorsque les prestations dispensées au bénéficiaire comprennent des périodes de formation en situation de travail, le prestataire mobilise son réseau de partenaires socio-économiques pour coconstruire l\'ingénierie de formation et favoriser l\'accueil en entreprise.'],
        [29, 6, true, 'Le prestataire développe des actions qui concourent à l\'insertion professionnelle ou la poursuite d\'étude par la voie de l\'apprentissage ou par toute autre voie permettant de développer leurs connaissances et leurs compétences.'],

        // Critère 7 — Appréciations et réclamations
        [30, 7, false, 'Le prestataire recueille les appréciations des parties prenantes : bénéficiaires, financeurs, équipes pédagogiques et entreprise concernées.'],
        [31, 7, false, 'Le prestataire met en œuvre des modalités de traitement des difficultés rencontrées par les parties prenantes, des réclamations exprimées par ces dernières, des aléas survenus en cours de prestation.'],
        [32, 7, false, 'Le prestataire met en œuvre des mesures d\'amélioration à partir de l\'analyse des appréciations et des réclamations.'],
    ];
}
