<?php

namespace Database\Factories;

use App\Enums\AdmissionStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Models\Admission;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        return [
            'statut' => AdmissionStatut::AVerifier,
            'validated_by' => null,
            'validated_at' => null,
            'commentaire' => fake()->boolean(30) ? fake('fr_FR')->sentence(10) : null,
        ];
    }

    /**
     * Une admission officielle exige un contrat signé par les trois parties
     * dont le dossier OPCO est créé/transmis (invariants backend du cycle) :
     * la factory construit toute la chaîne valide, y compris pour les appels
     * historiques `create(['candidate_id' => X])`. Comme l'ouverture du
     * dossier OPCO déclenche déjà l'admission automatique, on réutilise le
     * dossier existant (une seule admission par contrat).
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $attributes = is_array($attributes) ? $attributes : [];

        $signe = [
            'statut_contrat' => ContractStatut::Complet,
            'statut_signature' => ContractSignatureStatut::Signe,
        ];

        $contract = match (true) {
            isset($attributes['contract_id']) => Contract::query()->findOrFail($attributes['contract_id']),
            isset($attributes['candidate_id']) => Contract::factory()->create($signe + ['candidate_id' => $attributes['candidate_id']]),
            default => Contract::factory()->create($signe),
        };

        unset($attributes['contract_id'], $attributes['candidate_id']);

        // Prérequis du cycle : dossier OPCO créé/transmis. Son hook `created`
        // ouvre déjà l'admission « À vérifier » — firstOrCreate la retrouve.
        $contract->opcoFile()->firstOrCreate([], ['statut' => OpcoStatut::PretDepot->value]);

        $admission = Admission::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            ['candidate_id' => $contract->candidate_id, 'statut' => AdmissionStatut::AVerifier->value],
        );

        $overrides = collect($this->definition())->merge($attributes)->all();

        $admission->fill($overrides)->save();

        return $admission->refresh();
    }
}
