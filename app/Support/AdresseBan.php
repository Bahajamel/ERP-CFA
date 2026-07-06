<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Autocomplétion d'adresse via la Base Adresse Nationale (api-adresse.data.gouv.fr).
 * Service public, gratuit, sans clé. Utilisé en recherche côté serveur dans le
 * formulaire candidat. En cas d'indisponibilité réseau, renvoie une liste vide —
 * la saisie manuelle reste toujours possible (fallback).
 */
class AdresseBan
{
    private const ENDPOINT = 'https://api-adresse.data.gouv.fr/search/';

    /**
     * Résultats de recherche pour un Select Filament : chaque clé encode
     * l'adresse structurée (JSON), chaque valeur est le libellé affiché.
     *
     * @return array<string, string>
     */
    public function options(string $recherche): array
    {
        $recherche = trim($recherche);

        if (mb_strlen($recherche) < 3) {
            return [];
        }

        try {
            $reponse = Http::timeout(5)->get(self::ENDPOINT, [
                'q' => $recherche,
                'limit' => 6,
                'autocomplete' => 1,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $reponse->successful()) {
            return [];
        }

        $options = [];

        foreach ($reponse->json('features') ?? [] as $feature) {
            $p = $feature['properties'] ?? [];
            $label = $p['label'] ?? null;

            if ($label === null) {
                continue;
            }

            $cle = json_encode([
                'adresse' => trim(($p['name'] ?? $label)),
                'code_postal' => $p['postcode'] ?? null,
                'ville' => $p['city'] ?? null,
                'pays' => 'France',
                'label' => $label,
            ], JSON_UNESCAPED_UNICODE);

            $options[$cle] = $label;
        }

        return $options;
    }

    /**
     * Décode la clé d'une option en adresse structurée.
     *
     * @return array{adresse:?string, code_postal:?string, ville:?string, pays:?string, label:?string}|null
     */
    public static function decode(?string $cle): ?array
    {
        if (blank($cle)) {
            return null;
        }

        $data = json_decode($cle, true);

        return is_array($data) ? $data : null;
    }
}
