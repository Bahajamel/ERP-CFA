<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\FinanceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceLine>
 */
class FinanceLineFactory extends Factory
{
    protected $model = FinanceLine::class;

    public function definition(): array
    {
        $attendu = fake()->randomElement([6000, 7200, 8000, 9500, 11000]);

        return [
            'contract_id' => Contract::factory(),
            'libelle' => fake()->randomElement([
                'Coût de formation — année 1',
                'Coût de formation — année 2',
                'Frais annexes',
            ]),
            'montant_attendu' => $attendu,
            'montant_accepte' => $attendu,
            'montant_bloque' => 0,
            'motif_blocage' => null,
        ];
    }

    /** Une part du montant est bloquée (avec motif obligatoire). */
    public function bloquee(float $montant = 1500): static
    {
        return $this->state(fn () => [
            'montant_bloque' => $montant,
            'motif_blocage' => 'En attente de justificatif OPCO',
        ]);
    }
}
