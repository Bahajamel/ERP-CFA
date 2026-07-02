<?php

namespace App\Livret;

use App\Models\CfaProfile;
use App\Models\Contract;
use App\Support\LivrableMissionMap;
use RuntimeException;

/**
 * Construit le payload JSON envoyé au service LivretRS à partir d'un contrat et
 * du profil du CFA. Le format reflète les modèles Pydantic de core/ (CFA,
 * DossierApprenant) et ajoute les pièces graphiques (logo/signature/cachet en
 * base64) et les options de génération (thème, format).
 *
 * Principe RGPD : on n'envoie QUE les données nécessaires aux livrables. Aucun
 * CERFA ni NIR n'est transmis.
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
        $profile = CfaProfile::current();

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
            'cfa' => $this->cfa($profile),
            'assets' => $this->assets($profile),
            'dossier' => $dossier,
            'livrables' => LivrableMissionMap::codes(),
            'theme_code' => $profile->theme_defaut ?: 'institutionnel',
            'format' => $profile->format_defaut ?: 'pdf',
            'verifier_rncp' => (bool) $profile->verifier_rncp,
        ];
    }

    /** Identité du CFA depuis le profil (singleton). */
    private function cfa(CfaProfile $p): array
    {
        return self::sansVides([
            'nom' => $p->nom ?: 'CFA',
            'raison_sociale' => $p->raison_sociale,
            'siren' => $p->siren,
            'siret' => $p->siret,
            'naf' => $p->naf,
            'nda' => $p->nda,
            'numero_uai' => $p->numero_uai,
            'adresse' => $p->adresse,
            'code_postal' => $p->code_postal,
            'ville' => $p->ville,
            'telephone' => $p->telephone,
            'email' => $p->email,
            'website' => $p->website,
            'representant_legal' => self::personne($p->representant_nom, $p->representant_prenom, $p->representant_fonction),
            'referent_pedagogique' => self::personne($p->referent_pedagogique_nom, $p->referent_pedagogique_prenom),
            'referent_handicap' => self::personne($p->referent_handicap_nom, $p->referent_handicap_prenom),
            'referent_mobilite' => self::personne($p->referent_mobilite_nom, $p->referent_mobilite_prenom),
            'dpo' => self::personne($p->dpo_nom, $p->dpo_prenom),
        ]);
    }

    /** Pièces graphiques encodées en base64 (logo, signature, cachet). */
    private function assets(CfaProfile $p): array
    {
        $assets = [];

        foreach (['logo', 'signature', 'cachet'] as $collection) {
            $media = $p->getFirstMedia($collection);
            if ($media !== null && is_file($media->getPath())) {
                $assets[$collection] = base64_encode((string) file_get_contents($media->getPath()));
            }
        }

        return $assets;
    }

    /** Objet Personne ({nom, prenom?, fonction?}) ou null si pas de nom. */
    private static function personne(?string $nom, ?string $prenom = null, ?string $fonction = null): ?array
    {
        if (blank($nom)) {
            return null;
        }

        return self::sansVides(['nom' => $nom, 'prenom' => $prenom, 'fonction' => $fonction]);
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
