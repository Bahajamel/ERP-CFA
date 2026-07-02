<?php

namespace Database\Factories;

use App\Enums\SeanceStatut;
use App\Models\Promotion;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    protected $model = Seance::class;

    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'heure_debut' => '09:00',
            'heure_fin' => '17:00',
            'libelle' => fake()->randomElement([
                'Mathématiques', 'Communication professionnelle', 'Atelier pratique',
                'Gestion de projet', 'Anglais', 'Culture générale',
            ]),
            'formateur_id' => null,
            'statut' => SeanceStatut::Planifiee,
        ];
    }
}
