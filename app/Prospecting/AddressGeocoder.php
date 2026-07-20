<?php

namespace App\Prospecting;

use Illuminate\Support\Facades\Http;

/**
 * Géocode une ville ou un code postal en coordonnées (lat/lon) via l'API Adresse
 * (Base Adresse Nationale), publique et gratuite. Permet de centrer la prospection
 * sur un lieu saisi plutôt que sur le seul point par défaut du CFA.
 */
class AddressGeocoder
{
    /**
     * @return array{lat: float, lon: float, label: string}|null Null si aucun résultat.
     */
    public function geocode(string $query): ?array
    {
        $config = config('services.geocoder');

        $request = Http::baseUrl($config['base_url'])
            ->acceptJson()
            ->timeout(10);

        if (($config['verify_ssl'] ?? true) === false) {
            $request->withoutVerifying();
        }

        $response = $request->get('/search/', ['q' => $query, 'limit' => 1]);

        if ($response->failed()) {
            return null;
        }

        $coordinates = data_get($response->json(), 'features.0.geometry.coordinates'); // [lon, lat]

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        return [
            'lon' => (float) $coordinates[0],
            'lat' => (float) $coordinates[1],
            'label' => (string) data_get($response->json(), 'features.0.properties.label', $query),
        ];
    }
}
