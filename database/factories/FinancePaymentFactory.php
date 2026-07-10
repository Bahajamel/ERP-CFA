<?php

namespace Database\Factories;

use App\Models\FinanceLine;
use App\Models\FinancePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancePayment>
 */
class FinancePaymentFactory extends Factory
{
    protected $model = FinancePayment::class;

    public function definition(): array
    {
        return [
            'finance_line_id' => FinanceLine::factory(),
            'invoice_id' => null,
            'montant' => fake()->randomElement([1500, 2000, 3000]),
            'date_paiement' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'moyen' => fake()->randomElement(['Virement', 'Chèque', 'Prélèvement']),
        ];
    }
}
