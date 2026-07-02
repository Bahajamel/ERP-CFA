<?php

namespace Database\Factories;

use App\Models\CfaMission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CfaMission>
 */
class CfaMissionFactory extends Factory
{
    protected $model = CfaMission::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numberBetween(1, 14),
            'code' => fake()->unique()->slug(2),
            'titre' => fake()->sentence(3),
            'texte' => fake()->paragraph(),
            'reference' => 'L6231-2',
        ];
    }
}
