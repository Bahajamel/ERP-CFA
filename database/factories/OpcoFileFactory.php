<?php

namespace Database\Factories;

use App\Enums\OpcoStatut;
use App\Models\Contract;
use App\Models\Opco;
use App\Models\OpcoFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpcoFile>
 */
class OpcoFileFactory extends Factory
{
    protected $model = OpcoFile::class;

    public function definition(): array
    {
        $statut = fake()->randomElement(OpcoStatut::cases());
        $estRejete = $statut === OpcoStatut::Rejete;

        return [
            'contract_id' => Contract::factory(),
            'opco_id' => Opco::factory(),
            'date_depot' => fake()->boolean(60) ? fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d') : null,
            'statut' => $statut,
            'montant_prevu' => fake()->randomFloat(2, 6000, 12000),
            'montant_accepte' => fake()->boolean(50) ? fake()->randomFloat(2, 6000, 12000) : null,
            'motif_rejet' => $estRejete ? fake('fr_FR')->sentence(8) : null,
            'responsable_correction_id' => null,
            'date_relance' => null,
            'commentaire_interne' => fake()->boolean(30) ? fake('fr_FR')->sentence(10) : null,
        ];
    }
}
