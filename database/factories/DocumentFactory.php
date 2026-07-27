<?php

namespace Database\Factories;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Candidate;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            // Par défaut rattaché à un candidat ; surchargeable via ->for(...) ou state.
            'documentable_id' => Candidate::factory(),
            'documentable_type' => Candidate::class,
            'type' => $type,
            'statut' => fake()->randomElement(DocumentStatut::cases()),
            'version' => 1,
            'previous_version_id' => null,
            'nom_fichier' => $type->value.'_'.fake()->numerify('####').'.pdf',
            'chemin' => 'documents/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }
}
