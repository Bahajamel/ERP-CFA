<?php

namespace Database\Factories;

use App\Enums\InvoiceStatut;
use App\Models\FinanceLine;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'finance_line_id' => FinanceLine::factory(),
            'statut' => InvoiceStatut::Brouillon,
            'destinataire' => fake('fr_FR')->company(),
            'montant' => fake()->randomElement([3000, 3600, 4000, 4750]),
            'date_echeance' => fake()->dateTimeBetween('+15 days', '+60 days')->format('Y-m-d'),
            'importee' => false,
        ];
    }

    /** Facture émise (n° de compta, datée). */
    public function emise(): static
    {
        return $this->state(fn () => [
            'statut' => InvoiceStatut::Emise,
            'numero' => now()->format('Y').'-'.fake()->unique()->numberBetween(1000, 9999),
            'date_emission' => now()->toDateString(),
        ]);
    }
}
