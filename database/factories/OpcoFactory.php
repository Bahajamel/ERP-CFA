<?php

namespace Database\Factories;

use App\Models\Opco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opco>
 */
class OpcoFactory extends Factory
{
    protected $model = Opco::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->randomElement([
                'OPCO EP', 'OPCO 2i', 'Atlas', 'Akto', 'OCAPIAT',
                'Constructys', 'OPCO Mobilités', 'Afdas', 'OPCO Santé', 'Uniformation',
            ]),
        ];
    }
}
