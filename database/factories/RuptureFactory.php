<?php

namespace Database\Factories;

use App\Enums\ContractStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Models\Contract;
use App\Models\Rupture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rupture>
 */
class RuptureFactory extends Factory
{
    protected $model = Rupture::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory()->state(['statut_contrat' => ContractStatut::Actif]),
            'date_rupture' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'motif' => fake()->randomElement(RuptureMotif::cases()),
            'initiative' => fake()->randomElement(['Employeur', 'Apprenti', 'Commun accord']),
            'statut' => RuptureStatut::Ouverte,
            'accompagnement' => null,
            'nouvel_employeur' => null,
        ];
    }
}
