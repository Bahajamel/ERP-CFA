<?php

namespace Database\Factories;

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'nom' => fake('fr_FR')->lastName(),
            'prenom' => fake('fr_FR')->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake('fr_FR')->mobileNumber(),
            'date_naissance' => fake()->dateTimeBetween('-25 years', '-17 years')->format('Y-m-d'),
            'adresse' => fake('fr_FR')->address(),
            'formation_visee_id' => Formation::factory(),
            'niveau_actuel' => fake()->randomElement(['Bac', 'Bac+1', 'Bac+2', 'Bac+3']),
            'mobilite' => fake()->randomElement(['Locale', 'Régionale', 'Nationale']),
            'disponibilite' => fake()->randomElement(['Immédiate', 'Sous 1 mois', 'Rentrée septembre']),
            'source' => fake()->randomElement(['Salon', 'Site web', 'Recommandation', 'Partenaire', 'Réseaux sociaux', 'France Travail']),
            'commercial_id' => User::factory(),
            'statut' => fake()->randomElement(CandidateStatut::cases()),
        ];
    }

    /** Candidat injoignable (ni email ni téléphone) — pour tester la règle métier. */
    public function sansContact(): static
    {
        return $this->state(fn () => ['email' => null, 'telephone' => null]);
    }

    /** Apprenant rattaché à une classe (pivot), avec la formation cohérente. */
    public function dansClasse(Promotion $classe): static
    {
        return $this
            ->state(fn () => ['formation_visee_id' => $classe->formation_id])
            ->afterCreating(fn (Candidate $candidate) => $candidate->promotions()->attach($classe->id));
    }
}
