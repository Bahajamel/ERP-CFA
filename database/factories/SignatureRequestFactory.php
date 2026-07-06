<?php

namespace Database\Factories;

use App\Enums\SignatureRequestStatut;
use App\Models\Contract;
use App\Models\SignatureRequest;
use App\Services\SignatureService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignatureRequest>
 */
class SignatureRequestFactory extends Factory
{
    protected $model = SignatureRequest::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'provider' => 'simulation',
            'external_id' => 'SIMU-'.fake()->unique()->numberBetween(1, 99999),
            'statut' => SignatureRequestStatut::Envoyee->value,
            'signataires' => [
                [
                    'role' => SignatureService::ROLE_APPRENTI,
                    'libelle' => 'Apprenti',
                    'nom' => fake('fr_FR')->name(),
                    'email' => fake()->safeEmail(),
                    'ordre' => 1,
                    'signe_at' => null,
                ],
                [
                    'role' => SignatureService::ROLE_CFA,
                    'libelle' => 'CFA',
                    'nom' => 'Direction CFA',
                    'email' => fake()->safeEmail(),
                    'ordre' => 2,
                    'signe_at' => null,
                ],
            ],
            'sent_at' => now(),
        ];
    }
}
