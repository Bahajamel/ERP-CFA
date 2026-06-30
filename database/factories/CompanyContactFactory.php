<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyContact>
 */
class CompanyContactFactory extends Factory
{
    protected $model = CompanyContact::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nom' => fake('fr_FR')->lastName(),
            'prenom' => fake('fr_FR')->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake('fr_FR')->phoneNumber(),
            'fonction' => fake()->randomElement(['Gérant', 'DRH', 'Responsable RH', 'Chef d\'équipe', 'Directeur', 'Responsable formation']),
            'is_principal' => false,
            'is_tuteur' => false,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn () => ['is_principal' => true]);
    }

    public function tuteur(): static
    {
        return $this->state(fn () => ['is_tuteur' => true, 'fonction' => 'Maître d\'apprentissage']);
    }
}
