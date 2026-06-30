<?php

namespace Database\Factories;

use App\Enums\NeedStatut;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Need>
 */
class NeedFactory extends Factory
{
    protected $model = Need::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'intitule_poste' => fake()->randomElement([
                'Assistant commercial', 'Chargé de clientèle', 'Vendeur conseil',
                'Développeur web junior', 'Technicien support', 'Assistant RH',
                'Comptable junior', 'Assistant de gestion', 'Chargé de marketing digital',
            ]),
            'formation_id' => Formation::factory(),
            'localisation' => fake('fr_FR')->city(),
            'date_demarrage' => fake()->dateTimeBetween('now', '+4 months')->format('Y-m-d'),
            'nb_postes' => fake()->numberBetween(1, 3),
            'rythme' => fake()->randomElement([
                '2 jours CFA / 3 jours entreprise',
                '1 semaine CFA / 1 semaine entreprise',
            ]),
            'prerequis' => fake()->boolean(60) ? fake('fr_FR')->sentence(8) : null,
            'contact_id' => null,
            'tuteur_id' => null,
            'statut' => fake()->randomElement(NeedStatut::cases()),
        ];
    }
}
