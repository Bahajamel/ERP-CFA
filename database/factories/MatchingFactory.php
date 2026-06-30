<?php

namespace Database\Factories;

use App\Enums\MatchingStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matching>
 */
class MatchingFactory extends Factory
{
    protected $model = Matching::class;

    public function definition(): array
    {
        $statut = fake()->randomElement(MatchingStatut::cases());

        return [
            'need_id' => Need::factory(),
            'candidate_id' => Candidate::factory(),
            'statut' => $statut,
            'cv_envoye' => fake()->boolean(70),
            'date_entretien' => fake()->boolean(40) ? fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d') : null,
            'retour_entreprise' => fake()->boolean(30) ? fake('fr_FR')->sentence(10) : null,
            'assigned_by' => User::factory(),
        ];
    }
}
