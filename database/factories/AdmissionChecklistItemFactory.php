<?php

namespace Database\Factories;

use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use App\Models\Admission;
use App\Models\AdmissionChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionChecklistItem>
 */
class AdmissionChecklistItemFactory extends Factory
{
    protected $model = AdmissionChecklistItem::class;

    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'document_type' => fake()->randomElement(DocumentType::cases()),
            'est_obligatoire' => fake()->boolean(70),
            'statut' => fake()->randomElement(ChecklistItemStatut::cases()),
            'document_id' => null,
        ];
    }
}
