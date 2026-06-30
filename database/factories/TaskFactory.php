<?php

namespace Database\Factories;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'titre' => fake()->randomElement([
                'Relancer le candidat', 'Vérifier les pièces du dossier',
                'Envoyer le CV à l\'entreprise', 'Préparer le dépôt OPCO',
                'Corriger le dossier OPCO', 'Planifier l\'entretien',
                'Relancer la signature du contrat',
            ]),
            'description' => fake()->boolean(50) ? fake('fr_FR')->sentence(12) : null,
            // taskable laissé nul par défaut (nullableMorphs) ; rattachable via ->for(...).
            'taskable_id' => null,
            'taskable_type' => null,
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'due_date' => fake()->dateTimeBetween('-1 week', '+3 weeks')->format('Y-m-d'),
            'priorite' => fake()->randomElement(TaskPriorite::cases()),
            'statut' => fake()->randomElement(TaskStatut::cases()),
            'source' => fake()->randomElement(['manuel', 'auto']),
        ];
    }
}
