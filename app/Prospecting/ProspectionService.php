<?php

namespace App\Prospecting;

use App\Enums\CompanyStatut;
use App\Enums\NeedStatut;
use App\Models\Company;
use App\Models\Formation;

/**
 * Prospection d'entreprises pour une formation via La Bonne Alternance :
 * recherche les entreprises qui recrutent, puis les importe dans le CRM comme
 * prospects, avec un besoin de recrutement rattaché à la formation ciblée.
 *
 * Dédoublonnage par SIRET (unique) : une entreprise déjà connue est réutilisée,
 * jamais écrasée (on préserve les données partenaires existantes).
 */
class ProspectionService
{
    public function __construct(private readonly LaBonneAlternanceClient $client) {}

    /**
     * @return array{found:int, imported:int, linked:int, skipped:int}
     */
    public function prospectForFormation(Formation $formation, float $latitude, float $longitude, ?int $radius = null, ?string $dateDebut = null): array
    {
        $prospects = $this->client->searchForFormation($formation, $latitude, $longitude, $radius);

        $imported = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($prospects as $prospect) {
            // Sans SIRET, impossible de créer/dédoublonner une entreprise fiable.
            if (blank($prospect->siret)) {
                $skipped++;

                continue;
            }

            [$company, $estNouvelle] = $this->trouverOuCreerEntreprise($prospect);
            $estNouvelle ? $imported++ : $linked++;

            $this->rattacherBesoin($company, $formation, $prospect, $dateDebut);
        }

        return [
            'found' => $prospects->count(),
            'imported' => $imported,
            'linked' => $linked,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{0: Company, 1: bool}  L'entreprise et un booléen « nouvellement créée ».
     */
    private function trouverOuCreerEntreprise(RecruitingCompany $prospect): array
    {
        $existante = Company::withTrashed()->where('siret', $prospect->siret)->first();

        if ($existante !== null) {
            return [$existante, false];
        }

        $company = Company::create([
            'raison_sociale' => $prospect->name,
            'siret' => $prospect->siret,
            'secteur' => $prospect->secteur,
            'adresse' => $prospect->adresse,
            'statut' => CompanyStatut::Prospect,
        ]);

        return [$company, true];
    }

    /**
     * Crée un besoin « à qualifier » pour la formation ciblée, sauf si un besoin
     * pour cette même formation existe déjà chez l'entreprise (pas de doublon).
     */
    private function rattacherBesoin(Company $company, Formation $formation, RecruitingCompany $prospect, ?string $dateDebut = null): void
    {
        if ($company->needs()->where('formation_id', $formation->id)->exists()) {
            return;
        }

        $company->needs()->create([
            'intitule_poste' => 'Alternance — '.$formation->libelle,
            'formation_id' => $formation->id,
            'statut' => NeedStatut::Cree,
            'nb_postes' => 1,
            'localisation' => $prospect->adresse,
            'date_demarrage' => $dateDebut,
        ]);
    }
}
