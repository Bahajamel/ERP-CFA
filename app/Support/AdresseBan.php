<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
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

        $reponse = $this->appel($recherche);

        if ($reponse === null || ! $reponse->successful()) {
            return [];
        }

        $options = [];

        // Numéro éventuellement saisi par l'utilisateur : il sert de repli quand
        // la BAN ne connaît pas ce numéro et dégrade la réponse au niveau rue.
        $numeroSaisi = self::numeroEnTete($recherche);

        foreach ($reponse->json('features') ?? [] as $feature) {
            $p = $feature['properties'] ?? [];
            $label = $p['label'] ?? null;

            if ($label === null) {
                continue;
            }

            // La BAN renvoie la géométrie en [longitude, latitude].
            $coords = $feature['geometry']['coordinates'] ?? [];

            // « housenumber » et « street » ne sont présents que sur les
            // résultats de type adresse précise ; « name » les fusionne
            // (« 59 Rue la Fayette »). On retient le numéro de la BAN en
            // priorité, sinon celui saisi si elle n'a répondu qu'à la rue.
            $rue = $p['street'] ?? null;
            $voie = $rue ?? $p['name'] ?? $label;
            $numero = $p['housenumber'] ?? ($rue !== null ? $numeroSaisi : null);

            $adresse = trim(implode(' ', array_filter([$numero, $voie])));

            $cle = json_encode([
                'numero' => $numero,
                'voie' => $voie,
                'adresse' => $adresse !== '' ? $adresse : trim($p['name'] ?? $label),
                'code_postal' => $p['postcode'] ?? null,
                'ville' => $p['city'] ?? null,
                'pays' => 'France',
                'latitude' => isset($coords[1]) ? (float) $coords[1] : null,
                'longitude' => isset($coords[0]) ? (float) $coords[0] : null,
                'label' => $label,
            ], JSON_UNESCAPED_UNICODE);

            // Le numéro repris de la saisie n'apparaît pas dans le libellé BAN :
            // on le préfixe pour que l'utilisateur voie ce qui sera enregistré.
            $options[$cle] = $numero !== null && ! str_starts_with($label, $numero)
                ? $numero.' '.$label
                : $label;
        }

        return $options;
    }

    /**
     * Numéro de voirie en tête de la recherche (« 228 rue … » → « 228 »,
     * « 12 bis avenue … » → « 12 bis »). Un nombre à 5 chiffres est un code
     * postal, pas un numéro de voirie : il est ignoré.
     */
    private static function numeroEnTete(string $recherche): ?string
    {
        if (! preg_match('/^\s*(\d{1,4}(?:\s*(?:bis|ter|quater))?)(?=[\s,])/iu', $recherche, $m)) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($m[1]));
    }

    /**
     * Appel HTTP à la BAN. En local, si le poste n'a pas de bundle CA configuré
     * (erreur cURL 60 fréquente sous Windows), on retente sans vérification SSL
     * pour ne pas bloquer la saisie d'adresse en dev. En production, la
     * vérification SSL reste stricte.
     */
    private function appel(string $recherche): ?Response
    {
        $params = ['q' => $recherche, 'limit' => 6, 'autocomplete' => 1];

        try {
            return Http::timeout(5)->get(self::ENDPOINT, $params);
        } catch (Throwable $e) {
            if (app()->environment('local')) {
                try {
                    return Http::timeout(5)->withoutVerifying()->get(self::ENDPOINT, $params);
                } catch (Throwable) {
                    return null;
                }
            }

            report($e);

            return null;
        }
    }

    /**
     * Décode la clé d'une option en adresse structurée.
     *
     * `numero` et `voie` sont séparés (pour les formulaires qui exposent une
     * case « Numéro ») ; `adresse` reste la voie complète (numéro + rue) pour
     * ceux qui n'ont qu'un seul champ.
     *
     * @return array{numero:?string, voie:?string, adresse:?string, code_postal:?string, ville:?string, pays:?string, latitude:?float, longitude:?float, label:?string}|null
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
