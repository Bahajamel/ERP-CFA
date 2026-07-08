<?php

namespace Database\Factories;

use App\Enums\CandidateStatut;
use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Models\Candidate;
use App\Models\Entretien;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entretien>
 */
class EntretienFactory extends Factory
{
    protected $model = Entretien::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory()->state(['statut' => CandidateStatut::EntretienAPlanifier]),
            'responsable_id' => User::factory(),
            'mode' => EntretienMode::Presentiel->value,
            'statut' => EntretienStatut::APlanifier->value,
        ];
    }

    /** Entretien planifié : créneau complet (le candidat passe à « Entretien prévu »). */
    public function planifie(): static
    {
        return $this->state(fn () => [
            'date_entretien' => now()->addDays(3)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'statut' => EntretienStatut::Planifie->value,
        ]);
    }

    /** Entretien réalisé (créneau passé), prêt pour la décision. */
    public function realise(): static
    {
        return $this->state(fn () => [
            'date_entretien' => now()->subDays(2)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'statut' => EntretienStatut::Realise->value,
        ]);
    }
}
