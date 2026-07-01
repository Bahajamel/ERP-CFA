<?php

namespace Database\Factories;

use App\Enums\InteractionType;
use App\Models\Company;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interaction>
 */
class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    public function definition(): array
    {
        return [
            // Par défaut rattachée à une entreprise ; surchargeable via ->for(...).
            'interactable_id' => Company::factory(),
            'interactable_type' => Company::class,
            'type' => fake()->randomElement(InteractionType::cases()),
            'date_interaction' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'resume' => fake('fr_FR')->sentence(12),
            'prochaine_action' => null,
            'prochaine_action_le' => null,
            'user_id' => User::factory(),
        ];
    }

    /** Avec une relance planifiée à une date donnée (par défaut dans 7 jours). */
    public function avecRelance(?string $date = null): static
    {
        return $this->state(fn (): array => [
            'prochaine_action' => 'Relancer pour la signature du contrat',
            'prochaine_action_le' => $date ?? now()->addDays(7)->toDateString(),
        ]);
    }
}
