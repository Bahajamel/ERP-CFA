<?php

namespace Database\Factories;

use App\Enums\EvaluationType;
use App\Models\Candidate;
use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'promotion_id' => null,
            'matiere' => fake()->randomElement(['Développement web', 'Anglais professionnel', 'Bases de données']),
            'type' => fake()->randomElement(EvaluationType::cases()),
            'note' => fake()->randomFloat(2, 5, 20),
            'bareme' => 20,
            'coefficient' => fake()->randomElement([1, 1, 2, 3]),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
