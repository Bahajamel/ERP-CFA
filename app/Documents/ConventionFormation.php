<?php

namespace App\Documents;

use App\Models\Contract;
use App\Models\Organisation;
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
        $cfa = Organisation::courante();
        $principal = $co?->contactPrincipal()->first();
        $repEnt = $co?->representantsLegaux()->first();
        $opco = $contract->opcoFile?->opco ?? $co?->opco;
        $respPeda = $contract->responsablePedagogique;

        $totalHeures = (int) $contract->duree_formation_heures;
        $distance = (int) $contract->heures_elearning + (int) $contract->heures_classe_virtuelle;
        $modalite = $totalHeures > 0 && $distance >= $totalHeures
            ? 'À distance'
            : ($distance > 0 ? 'Mixte (présentiel et à distance)' : 'En présentiel');

        $repere = config('apprentissage.frais_annexes');

        return [
            // ---- CFA ----
            'cfa_designation' => $cfa->designation(),
            'cfa_adresse' => $this->adresse($cfa->adresse, $cfa->code_postal, $cfa->ville),
            'cfa_siret' => $this->siret($cfa->siret),
            'cfa_uai' => $cfa->numero_uai,
            'cfa_nda' => $cfa->nda,
            'cfa_representant' => $this->personne($cfa->representant_prenom, $cfa->representant_nom),
            'cfa_representant_qualite' => $cfa->representant_fonction,

            // ---- Entreprise ----
            'entreprise_designation' => $co?->raison_sociale,
            'entreprise_adresse' => $this->adresse($co?->adresse, $co?->code_postal, $co?->ville),
            'entreprise_siret' => $this->siret($co?->siret),
            'entreprise_opco' => $opco?->nom,
            'entreprise_idcc' => $co?->code_idcc,
            'entreprise_convention_collective' => $co?->convention_collective,
            'entreprise_representant' => $this->personne($repEnt?->prenom, $repEnt?->nom),
            'entreprise_representant_qualite' => $repEnt?->fonction,
            'corr_nom' => $principal?->nom,
            'corr_prenom' => $principal?->prenom,
            'corr_tel' => $principal?->telephone,
            'corr_mail' => $principal?->email,

            // ---- Article 1 : objet ----
            'formation_intitule' => $formation?->libelle,
            'formation_rncp' => $contract->code_rncp ?? $formation?->code_rncp,
            'formation_niveau' => $formation?->niveau,
            'formation_code_diplome' => $formation?->code_diplome,

            // ---- Article 2 : modalités ----
            'lieu_formation' => $contract->lieuFormationLisible() ?? $this->adresse($cfa->adresse, $cfa->code_postal, $cfa->ville),
            'modalites' => $modalite,
            'duree_heures' => $totalHeures > 0 ? $totalHeures.' heures' : null,
            'date_cfa_debut' => $this->dateFr($contract->date_debut),
            'date_cfa_fin' => $this->dateFr($contract->date_fin),
            'frais_examen' => number_format((float) $repere['premier_equipement'], 0, ',', ' '),

            // ---- Article 3 : suivi ----
            'temps_travail' => $contract->duree_hebdo_heures ? $contract->duree_hebdo_heures.' heures' : '35 heures',
            'resp_peda_nom' => $respPeda?->name ?: $this->personne($cfa->referent_pedagogique_prenom, $cfa->referent_pedagogique_nom),
            'resp_peda_email' => $respPeda?->email ?: $cfa->email,
            'maitre_nom' => $tuteur?->nom,
            'maitre_prenom' => $tuteur?->prenom,
            'maitre_mail' => $tuteur?->email,
            'maitre_tel' => $tuteur?->telephone,
            'maitre_poste' => $tuteur?->fonction,

            // ---- Article 4 : bénéficiaire ----
            'apprenti_nom' => $cand?->nom,
            'apprenti_prenom' => $cand?->prenom,
            'apprenti_adresse' => $this->adresse($cand?->adresse, $cand?->code_postal, $cand?->ville),
            'apprenti_naissance' => $this->dateFr($cand?->date_naissance),
            'apprenti_email' => $cand?->email,
            'date_debut' => $this->dateFr($contract->dateDebutEffective()),
            'date_fin' => $this->dateFr($contract->dateFinEffective()),

            // ---- Article 5 : dispositions financières ----
            'financement' => $this->calendrierFinancement($contract),
            'cout_total' => $contract->cout_formation !== null ? $this->euros($contract->cout_formation) : null,

            // ---- Article 6 : frais annexes ----
            'frais_hebergement' => $contract->frais_hebergement,
            'frais_restauration' => $contract->frais_restauration,
            'frais_equipement' => $contract->frais_equipement,
            'frais_mobilite' => $contract->frais_mobilite,
            'majoration_rqth' => $cand?->rqth,
            'plafond_equipement' => number_format((float) $repere['premier_equipement'], 0, ',', ' '),

            // ---- Signatures ----
            'fait_a' => $cfa->ville,
            'fait_le' => now()->translatedFormat('d/m/Y'),
            'cfa_signature_image' => $this->image($cfa, 'signature'),
            'cfa_cachet_image' => $this->image($cfa, 'cachet'),
        ];
    }

    /**
     * Tableau financier par année d'exécution (Article 5), depuis le calendrier
     * de financement saisi (onglet Contrat) ; repli sur les montants globaux du
     * contrat pour la 1re année si le calendrier n'est pas encore renseigné.
     *
     * @return list<array{annee:int, prestation:mixed, opco:mixed, reste:mixed}>
     */
    private function calendrierFinancement(Contract $contract): array
    {
        $rows = $contract->calendrier_financement ?? [];

        $out = [];
        for ($n = 1; $n <= 3; $n++) {
            $r = collect($rows)->first(fn ($x) => (int) ($x['annee'] ?? 0) === $n) ?? ($rows[$n - 1] ?? []);

            $reste = $r['reste_a_charge'] ?? ($n === 1
                ? ($contract->net_a_payer ?? ($contract->reste_a_charge_zero ? 0 : null))
                : null);

            $out[] = [
                'annee' => $n,
                'prestation' => $r['formation'] ?? ($n === 1 ? $contract->cout_formation : null),
                'opco' => $r['financement'] ?? ($n === 1 ? ($contract->engagement_opco_total ?? $contract->npec_annuel) : null),
                'reste' => $reste,
            ];
        }

        return $out;
    }

    /**
     * Pièce graphique du CFA en data-URI, pour dompdf.
     *
     * Le fichier doit exister RÉELLEMENT sur le disque : une balise <img> sur un
     * média fantôme laisserait un cadre vide au bas d'une convention présentée
     * comme signée. Absent = on retombe sur la mention « à signer », jamais sur
     * une signature muette.
     */
    private function image(Organisation $cfa, string $collection): ?string
    {
        $media = $cfa->getFirstMedia($collection);

        if ($media === null || ! is_file($media->getPath())) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'
            .base64_encode((string) file_get_contents($media->getPath()));
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
