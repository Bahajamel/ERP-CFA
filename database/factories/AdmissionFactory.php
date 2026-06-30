<?php

namespace Database\Factories;

use App\Enums\AdmissionStatut;
use App\Models\Admission;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'statut' => fake()->randomElement(AdmissionStatut::cases()),
            'validated_by' => null,
            'validated_at' => null,
            'commentaire' => fake()->boolean(30) ? fake('fr_FR')->sentence(10) : null,
        ];
    }
}
