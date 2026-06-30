<?php

namespace Database\Factories;

use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formation>
 */
class FormationFactory extends Factory
{
    protected $model = Formation::class;

    public function definition(): array
    {
        $libelle = fake()->randomElement([
            'BTS Management Commercial Opérationnel',
            'BTS Négociation et Digitalisation de la Relation Client',
            'BTS Comptabilité et Gestion',
            'BTS Support à l\'Action Managériale',
            'BTS Gestion de la PME',
            'BTS Services Informatiques aux Organisations',
            'Bachelor Marketing Digital',
            'Bachelor Ressources Humaines',
            'Titre Professionnel Développeur Web et Web Mobile',
            'Master Management et Administration des Entreprises',
        ]);

        return [
            'libelle' => $libelle,
            'code_rncp' => 'RNCP'.fake()->numberBetween(30000, 39999),
            'niveau' => fake()->randomElement(['3', '4', '5', '6', '7']),
            'duree_mois' => fake()->randomElement([12, 24]),
            'rythme_defaut' => fake()->randomElement([
                '2 jours CFA / 3 jours entreprise',
                '1 semaine CFA / 1 semaine entreprise',
                '3 jours CFA / 2 jours entreprise',
            ]),
            'is_active' => true,
        ];
    }
}
