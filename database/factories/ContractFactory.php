<?php

namespace Database\Factories;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $debut = fake()->dateTimeBetween('-6 months', '+2 months');

        return [
            'candidate_id' => Candidate::factory(),
            'company_id' => Company::factory(),
            'formation_id' => Formation::factory(),
            'code_rncp' => 'RNCP'.fake()->numberBetween(30000, 39999),
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin' => (clone $debut)->modify('+24 months')->format('Y-m-d'),
            'tuteur_id' => null,
            'rythme' => fake()->randomElement([
                '2 jours CFA / 3 jours entreprise',
                '1 semaine CFA / 1 semaine entreprise',
            ]),
            'lieu_formation' => fake('fr_FR')->city(),
            'statut_signature' => fake()->randomElement(ContractSignatureStatut::cases()),
            'statut_contrat' => fake()->randomElement(ContractStatut::cases()),
        ];
    }
}
