<?php

namespace Database\Seeders;

use App\Models\CfaMission;
use Illuminate\Database\Seeder;

/**
 * Charge le référentiel des 14 missions du CFA — article L6231-2 du Code du
 * travail (rédaction issue de la loi n° 2018-771 du 5 septembre 2018 « pour la
 * liberté de choisir son avenir professionnel », en vigueur au 1er janvier 2019).
 *
 * Texte vérifié le 2026-07-02 sur la version consolidée Legifrance
 * (via code.travail.gouv.fr/code-du-travail/l6231-2). Les libellés (« titre »)
 * sont des intitulés courts internes ; « texte » reproduit l'alinéa officiel.
 *
 * Idempotent : la clé est le numéro d'alinéa ; rejouer le seeder met à jour le
 * référentiel sans dupliquer.
 */
class CfaMissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->missions() as [$numero, $code, $titre, $texte]) {
            CfaMission::query()->updateOrCreate(
                ['numero' => $numero],
                ['code' => $code, 'titre' => $titre, 'texte' => $texte, 'reference' => 'L6231-2'],
            );
        }
    }

    /** @return list<array{0:int,1:string,2:string,3:string}> [numéro, code, titre, texte officiel] */
    private function missions(): array
    {
        return [
            [1, 'orientation', 'Orientation et intégration (dont handicap)',
                "D'accompagner les personnes, y compris celles en situation de handicap, souhaitant s'orienter ou se réorienter par la voie de l'apprentissage, en développant leurs connaissances et leurs compétences et en facilitant leur intégration en emploi, en cohérence avec leur projet professionnel. Pour les personnes en situation de handicap, le centre de formation d'apprentis appuie la recherche d'un employeur et facilite leur intégration tant en centre de formation d'apprentis qu'en entreprise en proposant les adaptations nécessaires au bon déroulement de leur contrat d'apprentissage. Pour accomplir cette mission, le centre de formation d'apprentis désigne un référent chargé de l'intégration des personnes en situation de handicap ;"],

            [2, 'recherche_employeur', "Appui à la recherche d'un employeur",
                "D'appuyer et d'accompagner les postulants à l'apprentissage dans leur recherche d'un employeur ;"],

            [3, 'coherence_alternance', 'Cohérence CFA – entreprise',
                "D'assurer la cohérence entre la formation dispensée en leur sein et celle dispensée au sein de l'entreprise, en particulier en organisant la coopération entre les formateurs et les maîtres d'apprentissage ;"],

            [4, 'droits_devoirs', 'Information sur les droits et devoirs',
                "D'informer, dès le début de leur formation, les apprentis de leurs droits et devoirs en tant qu'apprentis et en tant que salariés et des règles applicables en matière de santé et de sécurité en milieu professionnel ;"],

            [5, 'continuite_rupture', 'Continuité de formation en cas de rupture',
                "De permettre aux apprentis en rupture de contrat la poursuite de leur formation pendant six mois tout en les accompagnant dans la recherche d'un nouvel employeur, en lien avec le service public de l'emploi. Les apprentis en rupture de contrat sont affiliés à un régime de sécurité sociale et peuvent bénéficier d'une rémunération, en application des dispositions prévues respectivement aux articles L. 6342-1 et L. 6341-1 ;"],

            [6, 'difficultes_sociales', 'Prévention des difficultés sociales et matérielles',
                "D'apporter, en lien avec le service public de l'emploi, en particulier avec les missions locales, un accompagnement aux apprentis pour prévenir ou résoudre les difficultés d'ordre social et matériel susceptibles de mettre en péril le déroulement du contrat d'apprentissage ;"],

            [7, 'mixite', 'Mixité et égalité femmes-hommes',
                "De favoriser la mixité au sein de leurs structures en sensibilisant les formateurs, les maîtres d'apprentissage et les apprentis à la question de l'égalité entre les femmes et les hommes ainsi qu'à la prévention du harcèlement sexuel au travail et en menant une politique d'orientation et de promotion des formations qui met en avant les avantages de la mixité. Ils participent à la lutte contre la répartition sexuée des métiers ;"],

            [8, 'egalite_professionnelle', 'Mixité des métiers et égalité professionnelle',
                "D'encourager la mixité des métiers et l'égalité professionnelle entre les femmes et les hommes en organisant des actions d'information sur ces sujets à destination des apprentis ;"],

            [9, 'diversite', 'Diversité et égalité des chances',
                "De favoriser, au-delà de l'égalité entre les femmes et les hommes, la diversité au sein de leurs structures en sensibilisant les formateurs, les maîtres d'apprentissage et les apprentis à l'égalité des chances et à la lutte contre toutes formes de discriminations et en menant une politique d'orientation et de promotion des formations qui mette en avant les avantages de la diversité ;"],

            [10, 'mobilite', 'Mobilité nationale et internationale',
                "D'encourager la mobilité nationale et internationale des apprentis en nommant un personnel dédié, qui peut comprendre un référent mobilité mobilisant, au niveau national, les ressources locales et, au niveau international, les programmes de l'Union européenne, et en mentionnant, le cas échéant, dans le contenu de la formation, la période de mobilité ;"],

            [11, 'suivi_distance', 'Suivi de la formation à distance',
                "D'assurer le suivi et l'accompagnement des apprentis quand la formation prévue au 2° de l'article L. 6211-2 est dispensée en tout ou partie à distance ;"],

            [12, 'evaluation_competences', 'Évaluation des compétences acquises',
                "D'évaluer les compétences acquises par les apprentis, y compris sous la forme d'un contrôle continu, dans le respect des règles définies par chaque organisme certificateur ;"],

            [13, 'accompagnement_sortie', "Accompagnement en cas d'interruption ou d'échec",
                "D'accompagner les apprentis ayant interrompu leur formation et ceux n'ayant pas, à l'issue de leur formation, obtenu de diplôme ou de titre à finalité professionnelle vers les personnes et les organismes susceptibles de les accompagner dans la définition d'un projet de poursuite de formation ;"],

            [14, 'acces_aides', 'Accès aux aides',
                "D'accompagner les apprentis dans leurs démarches pour accéder aux aides auxquelles ils peuvent prétendre au regard de la législation et de la réglementation en vigueur."],
        ];
    }
}
