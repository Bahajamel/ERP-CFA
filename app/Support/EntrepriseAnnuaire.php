<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Recherche d'entreprise par raison sociale via l'Annuaire des Entreprises
 * de l'État (recherche-entreprises.api.gouv.fr — gratuit, sans clé).
 * Alimente le formulaire entreprise : SIRET du siège, adresse structurée et
 * coordonnées GPS ; l'OPCO est ensuite détecté depuis le SIRET (France
 * Compétences). En cas d'indisponibilité, la saisie manuelle reste possible.
 */
class EntrepriseAnnuaire
{
    private const ENDPOINT = 'https://recherche-entreprises.api.gouv.fr/search';

    /** Sections NAF rév. 2 (INSEE) : lettre → libellé du secteur d'activité. */
    private const SECTIONS_NAF = [
        'A' => 'Agriculture, sylviculture et pêche',
        'B' => 'Industries extractives',
        'C' => 'Industrie manufacturière',
        'D' => 'Production et distribution d\'électricité, de gaz, de vapeur',
        'E' => 'Eau, assainissement, gestion des déchets et dépollution',
        'F' => 'Construction',
        'G' => 'Commerce ; réparation d\'automobiles et de motocycles',
        'H' => 'Transports et entreposage',
        'I' => 'Hébergement et restauration',
        'J' => 'Information et communication',
        'K' => 'Activités financières et d\'assurance',
        'L' => 'Activités immobilières',
        'M' => 'Activités spécialisées, scientifiques et techniques',
        'N' => 'Services administratifs et de soutien',
        'O' => 'Administration publique',
        'P' => 'Enseignement',
        'Q' => 'Santé humaine et action sociale',
        'R' => 'Arts, spectacles et activités récréatives',
        'S' => 'Autres activités de services',
        'T' => 'Activités des ménages en tant qu\'employeurs',
        'U' => 'Activités extra-territoriales',
    ];

    /**
     * Résultats pour un Select Filament : clé = fiche encodée (JSON),
     * valeur = libellé affiché.
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

        foreach ($reponse->json('results') ?? [] as $resultat) {
            $siege = $resultat['siege'] ?? [];
            $nom = $resultat['nom_raison_sociale'] ?? $resultat['nom_complet'] ?? null;
            $siret = $siege['siret'] ?? null;

            if ($nom === null || $siret === null) {
                continue;
            }

            $ville = $siege['libelle_commune'] ?? null;

            // Secteur = libellé de la section NAF (activité principale),
            // repli sur le code NAF brut si la section est inconnue.
            $secteur = self::SECTIONS_NAF[$resultat['section_activite_principale'] ?? '']
                ?? ($resultat['activite_principale'] ?? null);

            $cle = json_encode([
                'raison_sociale' => $nom,
                'siret' => $siret,
                'secteur' => $secteur,
                'adresse' => $this->voie($siege),
                'code_postal' => $siege['code_postal'] ?? null,
                'ville' => $ville,
                'pays' => 'France',
                'latitude' => isset($siege['latitude']) ? (float) $siege['latitude'] : null,
                'longitude' => isset($siege['longitude']) ? (float) $siege['longitude'] : null,
                'label' => $nom.($ville ? ' — '.$ville : '').' · SIRET '.$siret,
            ], JSON_UNESCAPED_UNICODE);

            $options[$cle] = $nom.($ville ? ' — '.$ville : '').' · '.$siret;
        }

        return $options;
    }

    /**
     * Décode la clé d'une option en fiche entreprise structurée.
     *
     * @return array{raison_sociale:?string, siret:?string, secteur:?string, adresse:?string, code_postal:?string, ville:?string, pays:?string, latitude:?float, longitude:?float, label:?string}|null
     */
    public static function decode(?string $cle): ?array
    {
        if (blank($cle)) {
            return null;
        }

        $data = json_decode($cle, true);

        return is_array($data) ? $data : null;
    }

    /** Voie du siège (« 43 AVENUE GABRIELLE »), repli sur l'adresse complète. */
    private function voie(array $siege): ?string
    {
        $voie = trim(implode(' ', array_filter([
            $siege['numero_voie'] ?? null,
            $siege['indice_repetition'] ?? null,
            $siege['type_voie'] ?? null,
            $siege['libelle_voie'] ?? null,
        ])));

        return $voie !== '' ? $voie : ($siege['adresse'] ?? null);
    }

    /** Appel HTTP, avec repli sans vérification SSL en local (poste sans bundle CA). */
    private function appel(string $recherche): ?Response
    {
        $params = ['q' => $recherche, 'per_page' => 6, 'page' => 1];

        try {
            return Http::timeout(6)->get(self::ENDPOINT, $params);
        } catch (Throwable $e) {
            if (app()->environment('local')) {
                try {
                    return Http::timeout(6)->withoutVerifying()->get(self::ENDPOINT, $params);
                } catch (Throwable) {
                    return null;
                }
            }

            report($e);

            return null;
        }
    }
}
