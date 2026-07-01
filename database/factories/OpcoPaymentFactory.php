<?php

namespace Database\Factories;

use App\Enums\PaymentStatut;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpcoPayment>
 */
class OpcoPaymentFactory extends Factory
{
    protected $model = OpcoPayment::class;

    public function definition(): array
    {
        return [
            'opco_file_id' => OpcoFile::factory(),
            'ordre' => 1,
            'libelle' => '1er versement (40 %)',
            'pourcentage' => 40,
            'montant_prevu' => fake()->randomFloat(2, 2000, 5000),
            'montant_verse' => null,
            'date_prevue' => fake()->dateTimeBetween('-2 months', '+6 months')->format('Y-m-d'),
            'statut' => PaymentStatut::Attendu,
        ];
    }
}
