<?php

namespace Database\Factories;

use App\Enums\PresenceStatut;
use App\Models\Candidate;
use App\Models\Presence;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presence>
 */
class PresenceFactory extends Factory
{
    protected $model = Presence::class;

    public function definition(): array
    {
        return [
            'seance_id' => Seance::factory(),
            'candidate_id' => Candidate::factory(),
            'statut' => PresenceStatut::NonRenseigne,
            'commentaire' => null,
        ];
    }
}
