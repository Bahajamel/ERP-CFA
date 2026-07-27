<?php

namespace Database\Factories;

use App\Enums\QualiopiStatut;
use App\Models\QualiopiIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualiopiIndicator>
 */
class QualiopiIndicatorFactory extends Factory
{
    protected $model = QualiopiIndicator::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numberBetween(1, 32),
            'critere' => fake()->numberBetween(1, 7),
            'libelle' => fake('fr_FR')->sentence(10),
            'specifique_cfa' => fake()->boolean(30),
            'statut' => fake()->randomElement(QualiopiStatut::cases()),
            'commentaire' => null,
            'reviewed_at' => null,
        ];
    }

    public function conforme(): static
    {
        return $this->state(['statut' => QualiopiStatut::Conforme]);
    }

    public function nonConforme(): static
    {
        return $this->state(['statut' => QualiopiStatut::NonConforme]);
    }
}
