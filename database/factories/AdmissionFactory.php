<?php

namespace Database\Factories;

use App\Enums\AdmissionStatut;
use App\Models\Admission;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * Un candidat porte toujours exactement un dossier d'admission, créé
     * automatiquement à sa création (CandidateObserver). On réutilise donc ce
     * dossier plutôt que d'en insérer un second (contrainte d'unicité).
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $attributes = is_array($attributes) ? $attributes : [];

        $candidateId = $attributes['candidate_id'] ?? Candidate::factory()->create()->id;
        unset($attributes['candidate_id']);

        $admission = Admission::query()->firstOrCreate(
            ['candidate_id' => $candidateId],
            ['statut' => AdmissionStatut::AVerifier->value],
        );

        $overrides = collect($this->definition())
            ->except('candidate_id')
            ->merge($attributes)
            ->all();

        $admission->fill($overrides)->save();

        return $admission;
    }
}
