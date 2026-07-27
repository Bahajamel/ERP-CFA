<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    public function definition(): array
    {
        $nom = 'CFA '.$this->faker->unique()->city();

        return [
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'actif' => true,
        ];
    }
}
