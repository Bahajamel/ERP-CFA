<?php

namespace Database\Factories;

use App\Enums\CompanyStatut;
use App\Models\Company;
use App\Models\Opco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $raison = fake('fr_FR')->company();

        return [
            'raison_sociale' => $raison,
            'nom_commercial' => fake()->boolean(40) ? $raison : null,
            'siret' => fake()->unique()->numerify('##############'),
            'adresse' => fake('fr_FR')->address(),
            'secteur' => fake()->randomElement([
                'Commerce', 'Informatique', 'BTP', 'Industrie', 'Services',
                'Restauration', 'Santé', 'Transport', 'Banque / Assurance',
            ]),
            'opco_id' => Opco::factory(),
            'statut' => fake()->randomElement(CompanyStatut::cases()),
        ];
    }
}
