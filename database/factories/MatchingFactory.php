<?php

namespace Database\Factories;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Models\User;
use App\Parcours\CycleApprenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matching>
 */
class MatchingFactory extends Factory
{
    protected $model = Matching::class;

    public function definition(): array
    {
        return [
            // Besoin ouvert par défaut : un matching « Accepté » sur un besoin
            // clôturé est interdit (P0-05-5). Surchargeable via ->for($besoin).
            'need_id' => Need::factory()->state(['statut' => NeedStatut::ProfilsEnvoyes]),
            // Cycle apprenant : seul un candidat accepté entre au Matching.
            'candidate_id' => Candidate::factory()->state(['statut' => CandidateStatut::Accepte]),
            'origine' => CycleApprenant::ORIGINE_CFA,
            'statut' => fake()->randomElement(MatchingStatut::cases()),
            'cv_envoye' => fake()->boolean(70),
            'date_entretien' => fake()->boolean(40) ? fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d') : null,
            'retour_entreprise' => fake()->boolean(30) ? fake('fr_FR')->sentence(10) : null,
            'assigned_by' => User::factory(),
        ];
    }
}
