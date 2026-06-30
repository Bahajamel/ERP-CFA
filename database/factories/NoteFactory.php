<?php

namespace Database\Factories;

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
            'contenu' => fake('fr_FR')->sentence(12),
            'author_id' => User::factory(),
        ];
    }
}
