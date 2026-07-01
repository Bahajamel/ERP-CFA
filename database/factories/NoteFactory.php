<?php

namespace Database\Factories;

use App\Enums\NoteType;
use App\Models\Candidate;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            // Par défaut rattachée à un candidat ; surchargeable via ->for(...).
            'notable_id' => Candidate::factory(),
            'notable_type' => Candidate::class,
            'type' => NoteType::Note,
            'contenu' => fake('fr_FR')->sentence(12),
            'satisfaction' => null,
            'author_id' => User::factory(),
        ];
    }

    /** Note de type « incident ». */
    public function incident(): static
    {
        return $this->state(fn (): array => ['type' => NoteType::Incident]);
    }

    /** Mesure de satisfaction (1 à 5). */
    public function satisfaction(int $niveau = 4): static
    {
        return $this->state(fn (): array => [
            'type' => NoteType::Satisfaction,
            'satisfaction' => $niveau,
        ]);
    }
}
