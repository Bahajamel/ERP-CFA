<?php

namespace Database\Seeders;

use App\Models\QualiopiIndicator;
use Illuminate\Database\Seeder;

/**
 * Charge le référentiel Qualiopi : 7 critères / 32 indicateurs du RNQ.
 * Idempotent : met à jour les libellés de référence sans écraser l'état de
 * conformité (statut, responsable, commentaire) déjà saisi par l'équipe.
 * `specifique_cfa` = indicateur portant une obligation spécifique aux CFA
 * (guide de lecture du RNQ) ; ces données restent éditables et à valider en interne.
 */
class QualiopiIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->indicateurs() as [$numero, $critere, $cfa, $libelle]) {
            QualiopiIndicator::query()->updateOrCreate(
                ['numero' => $numero],
                ['critere' => $critere, 'specifique_cfa' => $cfa, 'libelle' => $libelle],
            );
        }
    }

    /** @return list<array{0:int,1:int,2:bool,3:string}> [numéro, critère, spécifique CFA, libellé] */
    private function indicateurs(): array
    {
        return [
            // Critère 1 — Information du public
            [1, 1, true, 'Information accessible au public, détaillée et vérifiable sur les prestations proposées.'],
            [2, 1, false, 'Diffusion d\'indicateurs de résultats adaptés (obtention, insertion, rupture, poursuite d\'études).'],
            [3, 1, false, 'Information sur la certification visée : obtention, blocs de compétences, passerelles, débouchés.'],

            // Critère 2 — Objectifs et adaptation des prestations
            [4, 2, false, 'Analyse du besoin du bénéficiaire en lien avec l\'entreprise et le financeur.'],
            [5, 2, true, 'Objectifs de la prestation définis, mesurables et adaptés aux publics.'],
            [6, 2, false, 'Contenus et modalités adaptés aux objectifs et à la certification visée.'],
            [7, 2, false, 'Positionnement et évaluation des acquis à l\'entrée de la prestation.'],
            [8, 2, false, 'Contenus adaptés au niveau et aux prérequis des bénéficiaires.'],

            // Critère 3 — Accueil, accompagnement, suivi et évaluation
            [9, 3, true, 'Information sur les conditions de déroulement : durée, rythme, lieux, modalités.'],
            [10, 3, false, 'Adaptation de la prestation, accompagnement et modalités d\'évaluation.'],
            [11, 3, true, 'Suivi de l\'assiduité, de l\'engagement et de la progression des apprenants.'],
            [12, 3, false, 'Prise en compte et coordination des acteurs concourant à la prestation.'],
            [13, 3, false, 'Coordination des intervenants internes et externes à la prestation.'],
            [14, 3, true, 'Accompagnement socio-professionnel de l\'apprenant (droits, devoirs, vie quotidienne).'],
            [15, 3, false, 'Mesure de l\'atteinte des objectifs par les bénéficiaires.'],
            [16, 3, false, 'Description et mise en œuvre des moyens humains et techniques.'],

            // Critère 4 — Moyens pédagogiques, techniques et d'encadrement
            [17, 4, false, 'Moyens (locaux, équipements) adaptés à la prestation.'],
            [18, 4, true, 'Ressources pédagogiques mises à disposition et coordination pédagogique.'],
            [19, 4, false, 'Personnels dédiés à l\'accueil, l\'accompagnement et l\'appui des bénéficiaires.'],

            // Critère 5 — Compétences des personnels
            [20, 5, false, 'Compétences et qualifications des personnels mobilisés.'],
            [21, 5, false, 'Entretien et développement des compétences des personnels.'],
            [22, 5, true, 'Veille et actualisation des compétences des formateurs et des tuteurs.'],

            // Critère 6 — Environnement professionnel
            [23, 6, true, 'Veille légale et réglementaire et prise en compte de ses évolutions.'],
            [24, 6, true, 'Veille sur les évolutions des métiers, des emplois et des compétences.'],
            [25, 6, false, 'Veille sur les innovations pédagogiques et technologiques.'],
            [26, 6, true, 'Mobilisation de l\'écosystème : partenaires, entreprises, financeurs.'],
            [27, 6, true, 'Insertion professionnelle : relations avec les acteurs économiques du territoire.'],

            // Critère 7 — Appréciations et réclamations
            [28, 7, false, 'Recueil des appréciations des parties prenantes (bénéficiaires, entreprises, équipes).'],
            [29, 7, false, 'Traitement des réclamations, des difficultés et des aléas.'],
            [30, 7, false, 'Mise en œuvre de mesures d\'amélioration à partir des retours.'],
            [31, 7, false, 'Prise en compte des appréciations dans une démarche d\'amélioration continue.'],
            [32, 7, false, 'Traitement des aléas survenus en cours de prestation.'],
        ];
    }
}
