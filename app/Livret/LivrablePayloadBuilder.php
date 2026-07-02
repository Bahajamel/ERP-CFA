<?php

namespace App\Livret;

use App\Models\Contract;
use App\Support\LivrableMissionMap;
use RuntimeException;

/**
 * Construit le payload JSON envoyé au service LivretRS à partir d'un contrat.
 * Le format reflète les modèles Pydantic de core/ (CFA, DossierApprenant :
 * apprenant / employeur / maître d'apprentissage / contrat / formation).
 *
 * Principe RGPD : on n'envoie QUE les données nécessaires aux livrables
 * (identité de l'apprenti, entreprise, formation). Aucun CERFA ni NIR n'est
 * transmis — le service ne conserve rien.
 */
class LivrablePayloadBuilder
{
    public function pour(Contract $contract): array
    {
        $candidate = $contract->candidate;

        if ($candidate === null) {
            throw new RuntimeException("Le contrat n'est rattaché à aucun apprenti.");
        }

        $company = $contract->company;
        $formation = $contract->formation;
        $tuteur = $contract->tuteur;

        $dossier = self::sansVides([
            'apprenant' => self::sansVides([
                'nom' => $candidate->nom,
                'prenom' => $candidate->prenom,
                'date_naissance' => $candidate->date_naissance?->format('Y-m-d'),
                'email' => $candidate->email,
                'telephone' => $candidate->telephone,
                'adresse' => $candidate->adresse,
            ]),
            'employeur' => $company === null ? null : self::sansVides([
                'raison_sociale' => $company->raison_sociale,
                'siret' => $company->siret,
                'adresse' => $company->adresse,
            ]),
            'maitre_apprentissage' => $tuteur === null ? null : self::sansVides([
                'nom' => $tuteur->nom,
                'prenom' => $tuteur->prenom,
            ]),
            'contrat' => self::sansVides([
                'type_contrat' => 'Apprentissage',
                'date_debut' => $contract->date_debut?->format('Y-m-d'),
                'date_fin' => $contract->date_fin?->format('Y-m-d'),
            ]),
            'formation' => $formation === null ? null : self::sansVides([
                'intitule' => $formation->libelle,
                'rncp' => $formation->code_rncp,
                'niveau' => $formation->niveau,
            ]),
        ]);

        return [
            'cfa' => $this->cfa(),
            'dossier' => $dossier,
            'livrables' => LivrableMissionMap::codes(),
        ];
    }

    /** Identité du CFA depuis la configuration (config/cfa.php). */
    private function cfa(): array
    {
        $c = config('cfa');

        return self::sansVides([
            'nom' => $c['nom'] ?? 'CFA',
            'raison_sociale' => $c['raison_sociale'] ?? null,
            'siret' => $c['siret'] ?? null,
            'siren' => $c['siren'] ?? null,
            'naf' => $c['naf'] ?? null,
            'nda' => $c['nda'] ?? null,
            'numero_uai' => $c['numero_uai'] ?? null,
            'adresse' => $c['adresse'] ?? null,
            'code_postal' => $c['code_postal'] ?? null,
            'ville' => $c['ville'] ?? null,
            'telephone' => $c['telephone'] ?? null,
            'email' => $c['email'] ?? null,
            'website' => $c['website'] ?? null,
            'representant_legal' => self::personne($c['representant_legal'] ?? null),
            'referent_pedagogique' => self::personne($c['referent_pedagogique'] ?? null),
            'referent_handicap' => self::personne($c['referent_handicap'] ?? null),
        ]);
    }

    /** Convertit un référent de config en objet Personne, ou null si sans nom. */
    private static function personne(?array $data): ?array
    {
        if ($data === null || blank($data['nom'] ?? null)) {
            return null;
        }

        return self::sansVides([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'] ?? null,
            'fonction' => $data['fonction'] ?? null,
        ]);
    }

    /** Retire les valeurs nulles ou vides (chaîne « » ou tableau vide). */
    private static function sansVides(array $data): array
    {
        return array_filter(
            $data,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
