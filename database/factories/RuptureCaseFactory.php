<?php

namespace Database\Factories;

use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Models\Contract;
use App\Models\RuptureCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuptureCase>
 */
class RuptureCaseFactory extends Factory
{
    protected $model = RuptureCase::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'date_rupture' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'motif' => fake()->randomElement(RuptureMotif::cases())->value,
            'initiateur' => fake()->randomElement(RuptureInitiateur::cases())->value,
            'statut' => RuptureStatut::Ouvert->value,
            'motif_detail' => null,
            'recherche_employeur' => false,
            'nouvelle_company_id' => null,
            'responsable_id' => null,
            'date_cloture' => null,
        ];
    }
}
