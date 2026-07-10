<?php

namespace App\Documents;

use App\Models\CfaProfile;
use App\Models\Contract;
use App\Support\RemunerationApprenti;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Génère la « Convention de formation par apprentissage » (Annexe n°2 du
 * modèle officiel, art. L6353-1 & L6211-1 du Code du travail) pré-remplie à
 * partir d'un contrat de l'ERP, prête à imprimer / signer.
 *
 * Contrairement au CERFA (formulaire à cases → overlay FPDI par coordonnées),
 * la convention est un document texte à articles : on reproduit fidèlement le
 * modèle officiel dans une vue HTML (`documents.convention-formation`) puis on
 * la rend en PDF via dompdf. Les mêmes sources de données que le CERFA sont
 * réutilisées (candidat, entreprise, tuteur, formation, profil CFA, OPCO,
 * rémunération) pour garantir la cohérence entre les deux documents.
 */
class ConventionFormation
{
    /** Modèle officiel de référence embarqué (source du gabarit reproduit). */
    public const MODELE = 'documents/modele-convention-formation.docx';

    /** Génère la convention pré-remplie et retourne le PDF (octets bruts). */
    public function pour(Contract $contract): string
    {
        return Pdf::loadView('documents.convention-formation', [
            'd' => $this->donnees($contract),
        ])->setPaper('a4')->output();
    }

    /**
     * Toutes les valeurs de la convention, construites à partir du contrat et
     * des modèles liés. Les champs absents restent nuls : la vue affiche alors
     * des pointillés « à compléter » (comme le modèle officiel), jamais une
     * donnée inventée.
     *
     * @return array<string, mixed>
     */
    public function donnees(Contract $contract): array
    {
        $cand = $contract->candidate;
        $co = $contract->company;
        $tuteur = $contract->tuteur;
        $formation = $contract->formation ?? $cand?->formationVisee;
        $cfa = CfaProfile::current();
        $principal = $co?->contactPrincipal()->first();
        $opco = $contract->opcoFile?->opco ?? $co?->opco;

        return [
            // ---- CFA / organisme de formation ----
            'cfa_designation' => $cfa->raison_sociale ?: $cfa->nom,
            'cfa_adresse' => $this->adresse($cfa->adresse, $cfa->code_postal, $cfa->ville),
            'cfa_siret' => $this->siret($cfa->siret),
            'cfa_uai' => $cfa->numero_uai,
            'cfa_nda' => $cfa->nda,
            'cfa_representant' => $this->personne($cfa->representant_prenom, $cfa->representant_nom, $cfa->representant_fonction),
            'cfa_contact_nom' => $cfa->referent_pedagogique_nom,
            'cfa_contact_prenom' => $cfa->referent_pedagogique_prenom,
            'cfa_contact_email' => $cfa->email,
            'cfa_contact_tel' => $cfa->telephone,
            'cfa_ville' => $cfa->ville,

            // ---- Entreprise ----
            'entreprise_designation' => $co?->raison_sociale,
            'entreprise_adresse' => $this->adresse($co?->adresse, $co?->code_postal, $co?->ville),
            'entreprise_siret' => $this->siret($co?->siret),
            'entreprise_representant' => $principal
                ? $this->personne($principal->prenom, $principal->nom, $principal->fonction)
                : null,
            'entreprise_opco' => $opco?->nom,
            'entreprise_contact_nom' => $principal?->nom,
            'entreprise_contact_prenom' => $principal?->prenom,
            'entreprise_contact_email' => $principal?->email,
            'entreprise_contact_tel' => $principal?->telephone,

            // ---- Article 1 : objet ----
            'formation_intitule' => $formation?->libelle,
            'formation_rncp' => $contract->code_rncp ?? $formation?->code_rncp,
            'date_debut' => $this->dateFr($contract->date_debut),
            'date_fin' => $this->dateFr($contract->date_fin),
            'lieu_formation' => $contract->lieuFormationLisible(),
            'rythme' => $contract->rythme,

            // ---- Article 2 : modalités ----
            'nb_heures_total' => $contract->duree_formation_heures,

            // ---- Article 3 : bénéficiaire ----
            'apprenti_nom_complet' => $cand?->nom_complet,
            'apprenti_naissance' => $this->dateFr($cand?->date_naissance),
            'apprenti_adresse' => $this->adresse($cand?->adresse, $cand?->code_postal, $cand?->ville),

            // ---- Maître d'apprentissage (cohérence CERFA) ----
            'tuteur' => $tuteur ? $this->personne($tuteur->prenom, $tuteur->nom, $tuteur->fonction) : null,

            // ---- Article 4 : dispositions financières ----
            'cout_formation' => $contract->cout_formation !== null
                ? $this->euros($contract->cout_formation)
                : null,
            'opco_montant' => $contract->opcoFile?->montant_accepte ?? $contract->opcoFile?->montant_prevu,
            'annees_financement' => $this->anneesFinancement($contract, $cand?->date_naissance),

            // ---- Signatures ----
            'fait_a' => $cfa->ville,
            'fait_le' => now()->translatedFormat('d F Y'),
            'tribunal' => $cfa->ville,
        ];
    }

    /** Nombre d'années d'exécution du contrat (pour le tableau financier). */
    private function anneesFinancement(Contract $contract, mixed $dateNaissance): int
    {
        $periodes = RemunerationApprenti::periodes($dateNaissance, $contract->date_debut, $contract->date_fin);

        $annees = collect($periodes)->pluck('annee')->unique()->count();

        return max(1, min(4, $annees));
    }

    /** Assemble « Prénom NOM — fonction » en ignorant les parties absentes. */
    private function personne(?string $prenom, ?string $nom, ?string $fonction = null): ?string
    {
        $identite = trim(implode(' ', array_filter([$prenom, $nom])));

        if ($identite === '') {
            return null;
        }

        return filled($fonction) ? "{$identite} ({$fonction})" : $identite;
    }

    /** Adresse sur une ligne : « voie, CP ville » (parties absentes ignorées). */
    private function adresse(?string $voie, ?string $cp, ?string $ville): ?string
    {
        $localite = trim(implode(' ', array_filter([$cp, $ville])));

        $complet = trim(implode(', ', array_filter([
            filled($voie) ? trim($voie) : null,
            $localite !== '' ? $localite : null,
        ])));

        return $complet !== '' ? $complet : null;
    }

    /** Formate un SIRET « 123 456 789 00012 ». */
    private function siret(?string $siret): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $siret);

        if (strlen($chiffres) !== 14) {
            return $siret ?: null;
        }

        return substr($chiffres, 0, 3).' '.substr($chiffres, 3, 3).' '
            .substr($chiffres, 6, 3).' '.substr($chiffres, 9, 5);
    }

    /** Date lisible « 01/09/2026 » (null si absente). */
    private function dateFr(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return ($date instanceof Carbon ? $date : Carbon::parse($date))->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Montant en euros « 1 234,56 € ». */
    private function euros(mixed $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ').' €';
    }
}
