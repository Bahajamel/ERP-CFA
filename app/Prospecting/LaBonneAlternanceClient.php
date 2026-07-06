<?php

namespace App\Prospecting;

use App\Models\Formation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client HTTP de l'API La Bonne Alternance (api.apprentissage.beta.gouv.fr).
 * Authentification par jeton Bearer ; endpoint, chemin et clé de résultats sont
 * pilotés par la configuration (`config/services.php` → labonnealternance).
 */
class LaBonneAlternanceClient
{
    /** L'intégration est-elle utilisable (clé API renseignée) ? */
    public function isConfigured(): bool
    {
        return filled(config('services.labonnealternance.api_key'));
    }

    /**
     * Recherche brute d'entreprises qui recrutent en alternance.
     *
     * @param  array<string, mixed>  $params  romes, rncp, latitude, longitude, radius…
     * @return Collection<int, RecruitingCompany>
     */
    public function search(array $params): Collection
    {
        $config = config('services.labonnealternance');

        if (blank($config['api_key'])) {
            throw new RuntimeException('Clé API La Bonne Alternance absente (renseignez LBA_API_KEY).');
        }

        $response = Http::baseUrl($config['base_url'])
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout((int) $config['timeout'])
            ->get($config['search_path'], array_filter(
                $params,
                fn ($value): bool => $value !== null && $value !== '',
            ))
            ->throw();

        $items = $response->json($config['results_key']) ?? [];

        return collect($items)
            ->map(fn (array $item): RecruitingCompany => RecruitingCompany::fromApi($item))
            ->values();
    }

    /**
     * Entreprises qui recrutent pour une formation (via son code RNCP), autour
     * d'un point géographique. Retourne une collection vide si la formation n'a
     * pas de code RNCP (impossible de cibler le métier).
     *
     * @return Collection<int, RecruitingCompany>
     */
    public function searchForFormation(Formation $formation, float $latitude, float $longitude, ?int $radius = null): Collection
    {
        if (blank($formation->code_rncp)) {
            return collect();
        }

        return $this->search([
            'rncp' => $formation->code_rncp,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius ?? (int) config('services.labonnealternance.default_radius'),
        ]);
    }
}
