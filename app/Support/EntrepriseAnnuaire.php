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

            // État administratif : « F » (établissement fermé) au niveau du
            // siège, ou « C » (entreprise cessée) au niveau de l'unité légale.
            $ferme = ($siege['etat_administratif'] ?? null) === 'F'
                || ($resultat['etat_administratif'] ?? null) === 'C';

            $dirigeants = $this->dirigeants($resultat['dirigeants'] ?? []);
            $dirigeant = $dirigeants[0] ?? null;

            $cle = json_encode([
                'raison_sociale' => $nom,
                'nom_commercial' => $this->nomCommercial($siege),
                'siret' => $siret,
                'siren' => $resultat['siren'] ?? null,
                'secteur' => $secteur,
                'code_ape_naf' => $resultat['activite_principale'] ?? null,
                'activite_libelle' => self::libelleNaf($resultat['activite_principale'] ?? null),
                'code_idcc' => $this->idcc($siege),
                'forme_juridique' => $this->formeJuridique($resultat['nature_juridique'] ?? null),
                'dirigeants' => $dirigeants,
                'numero' => $this->numeroVoie($siege),
                'voie' => $this->voie($siege, avecNumero: false),
                'adresse' => $this->voie($siege),
                'complement_adresse' => $siege['complement_adresse'] ?? null,
                'code_postal' => $siege['code_postal'] ?? null,
                'ville' => $ville,
                'pays' => 'France',
                'latitude' => isset($siege['latitude']) ? (float) $siege['latitude'] : null,
                'longitude' => isset($siege['longitude']) ? (float) $siege['longitude'] : null,
                'dirigeant_nom' => $dirigeant['nom'] ?? null,
                'dirigeant_prenom' => $dirigeant['prenom'] ?? null,
                'dirigeant_qualite' => $dirigeant['qualite'] ?? null,
                'ferme' => $ferme,
                'date_fermeture' => $siege['date_fermeture'] ?? null,
                'label' => $nom.($ville ? ' — '.$ville : '').' · SIRET '.$siret.($ferme ? ' · ⚠ Fermé' : ''),
            ], JSON_UNESCAPED_UNICODE);

            $options[$cle] = $nom.($ville ? ' — '.$ville : '').' · '.$siret.($ferme ? ' · ⚠ Fermé' : '');
        }

        return $options;
    }

    /**
     * Décode la clé d'une option en fiche entreprise structurée.
     *
     * @return array{raison_sociale:?string, nom_commercial:?string, siret:?string, siren:?string, secteur:?string, code_ape_naf:?string, activite_libelle:?string, code_idcc:?string, forme_juridique:?string, dirigeants:array<int, array{nom:?string, prenom:?string, qualite:?string}>, numero:?string, voie:?string, adresse:?string, complement_adresse:?string, code_postal:?string, ville:?string, pays:?string, latitude:?float, longitude:?float, ferme:?bool, date_fermeture:?string, label:?string}|null
     */
    public static function decode(?string $cle): ?array
    {
        if (blank($cle)) {
            return null;
        }

        $data = json_decode($cle, true);

        return is_array($data) ? $data : null;
    }

    /** L'établissement de la fiche est-il administrativement fermé ? */
    public static function ferme(?array $fiche): bool
    {
        return (bool) ($fiche['ferme'] ?? false);
    }

    /**
     * État administratif d'un établissement par SIRET (F-09) : recherche
     * dans l'Annuaire et lecture de l'état du siège ou de l'établissement
     * correspondant. Null si l'API est indisponible ou le SIRET inconnu —
     * l'appelant ne doit alors rien conclure (contrôle jamais bloquant).
     *
     * @return array{ferme: bool, date_fermeture: ?string}|null
     */
    public function etatSiret(?string $siret): ?array
    {
        $siret = OpcoDetector::normaliserSiret($siret);

        if (! OpcoDetector::siretValide($siret)) {
            return null;
        }

        $reponse = $this->appel($siret);

        if ($reponse === null || ! $reponse->successful()) {
            return null;
        }

        foreach ($reponse->json('results') ?? [] as $resultat) {
            $cessee = ($resultat['etat_administratif'] ?? null) === 'C';

            // L'établissement exact prime (siège ou secondaire), l'état de
            // l'unité légale (entreprise cessée) s'applique dans tous les cas.
            foreach ([...($resultat['matching_etablissements'] ?? []), $resultat['siege'] ?? []] as $etablissement) {
                if (($etablissement['siret'] ?? null) === $siret) {
                    return [
                        'ferme' => $cessee || ($etablissement['etat_administratif'] ?? null) === 'F',
                        'date_fermeture' => $etablissement['date_fermeture'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Forme juridique lisible depuis la catégorie juridique INSEE (code à 4
     * chiffres) : libellé officiel de la nomenclature, avec repli sur la
     * grande famille (2 premiers chiffres) si le code exact est inconnu. Null
     * si inconnue — la saisie manuelle reste possible.
     */
    private function formeJuridique(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        return (config('categories_juridiques')[$code] ?? null) ?? match (substr($code, 0, 2)) {
            '10' => 'Entrepreneur individuel',
            '54' => 'SARL / EURL',
            '55', '56' => 'Société anonyme (SA)',
            '57' => 'Société par actions simplifiée (SAS)',
            '58' => 'Société en commandite',
            '62', '63' => 'Société coopérative',
            '65' => 'Société civile',
            '92' => 'Association',
            '93' => 'Fondation',
            default => null,
        };
    }

    /**
     * Libellé officiel de l'activité principale (NAF rév. 2). Le tableau est
     * indexé directement : les codes NAF contiennent un point (« 70.10Z »),
     * que la notation à points de config() prendrait pour un niveau imbriqué.
     */
    public static function libelleNaf(?string $code): ?string
    {
        return blank($code) ? null : (config('codes_naf')[$code] ?? null);
    }

    /**
     * Nom commercial du siège, ou première enseigne déclarée à défaut.
     * Souvent absent : la plupart des entreprises n'en déclarent pas.
     */
    private function nomCommercial(array $siege): ?string
    {
        if (filled($siege['nom_commercial'] ?? null)) {
            return $siege['nom_commercial'];
        }

        foreach ($siege['liste_enseignes'] ?? [] as $enseigne) {
            if (filled($enseigne)) {
                return $enseigne;
            }
        }

        return null;
    }

    /**
     * Convention collective applicable (IDCC). 9998 (« sans convention ») et
     * 9999 (« non renseignée ») sont des codes techniques, pas des branches :
     * ils sont ignorés, comme dans le calcul du NPEC.
     */
    private function idcc(array $siege): ?string
    {
        foreach ($siege['liste_idcc'] ?? [] as $idcc) {
            $idcc = trim((string) $idcc);

            if ($idcc !== '' && ! in_array($idcc, ['9998', '9999'], true)) {
                return $idcc;
            }
        }

        return null;
    }

    /**
     * Dirigeants personnes physiques (représentants légaux), décomposés en
     * nom / prénom / qualité (« Président », « Directeur général »…). Les
     * personnes morales (commissaires aux comptes, holdings) sont écartées :
     * le CERFA attend un représentant physique. Liste vide si aucun.
     *
     * @param  array<int, array<string, mixed>>  $dirigeants
     * @return array<int, array{nom: ?string, prenom: ?string, qualite: ?string}>
     */
    private function dirigeants(array $dirigeants): array
    {
        $retenus = [];

        foreach ($dirigeants as $d) {
            $physique = ($d['type_dirigeant'] ?? null) === 'personne physique'
                || filled($d['prenoms'] ?? $d['prenom'] ?? null);

            if (! $physique) {
                continue;
            }

            $nom = $d['nom'] ?? $d['nom_patronymique'] ?? null;
            $prenom = $d['prenoms'] ?? $d['prenom'] ?? null;

            if (filled($nom) || filled($prenom)) {
                $retenus[] = [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'qualite' => $d['qualite'] ?? null,
                ];
            }
        }

        // Le premier de la liste sert de représentant légal proposé : on place
        // devant les qualités qui engagent juridiquement la société, sinon un
        // simple administrateur passerait avant le gérant ou le président.
        usort($retenus, fn (array $a, array $b): int => $this->rangQualite($a['qualite']) <=> $this->rangQualite($b['qualite']));

        return $retenus;
    }

    /** Priorité d'une qualité de dirigeant comme représentant légal (0 = plus prioritaire). */
    private function rangQualite(?string $qualite): int
    {
        $q = mb_strtolower((string) $qualite);

        return match (true) {
            str_contains($q, 'gérant') => 0,
            str_contains($q, 'président') && str_contains($q, 'conseil') => 2,
            str_contains($q, 'président') => 1,
            str_contains($q, 'directeur général') => 3,
            str_contains($q, 'directeur') => 4,
            default => 5,
        };
    }

    /**
     * Voie du siège (« 43 AVENUE GABRIELLE »), repli sur l'adresse complète.
     * Avec `avecNumero: false`, seul le nom de la voie est renvoyé
     * (« AVENUE GABRIELLE ») pour alimenter une case « Rue » distincte de la
     * case « Numéro ».
     */
    private function voie(array $siege, bool $avecNumero = true): ?string
    {
        $voie = trim(implode(' ', array_filter([
            $avecNumero ? $this->numeroVoie($siege) : null,
            $siege['type_voie'] ?? null,
            $siege['libelle_voie'] ?? null,
        ])));

        return $voie !== '' ? $voie : ($siege['adresse'] ?? null);
    }

    /** Numéro de voirie du siège, indice de répétition inclus (« 12 B »). */
    private function numeroVoie(array $siege): ?string
    {
        $numero = trim(implode(' ', array_filter([
            $siege['numero_voie'] ?? null,
            $siege['indice_repetition'] ?? null,
        ])));

        return $numero !== '' ? $numero : null;
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
