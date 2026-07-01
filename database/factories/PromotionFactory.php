<?php

namespace Database\Factories;

use App\Models\Formation;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $annee = fake()->randomElement([2024, 2025, 2026]);

        return [
            'formation_id' => Formation::factory(),
            'libelle' => fake()->randomElement(['1ère année', '2ème année', 'Groupe A', 'Groupe B']),
            'annee_scolaire' => $annee.'-'.($annee + 1),
            'date_debut' => $annee.'-09-01',
            'date_fin' => ($annee + 1).'-08-31',
        ];
    }
}
