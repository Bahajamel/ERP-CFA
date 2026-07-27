<?php

namespace App\Cerfa;

use App\Models\Contract;
use App\Models\Organisation;
use App\Support\RemunerationApprenti;
use Carbon\Carbon;
use setasign\Fpdi\Fpdi;

/**
 * Remplit automatiquement le CERFA 10103*14 (contrat d'apprentissage) à
 * partir d'un contrat de l'ERP, prêt à imprimer / signer.
 *
 * Méthode : le formulaire officiel (2 pages A4) est importé tel quel via FPDI
 * — on n'analyse jamais de PDF externe, uniquement notre modèle embarqué —
 * puis chaque valeur est posée à la position EXACTE du champ correspondant.
 * Les coordonnées viennent de l'AcroForm officiel (config/cerfa_map.php),
 * donc le texte tombe précisément dans chaque case.
 */
class CerfaApprentissage
{
    /** Chemin du modèle officiel embarqué dans le dépôt. */
    private const TEMPLATE = 'cerfa/cerfa_10103-14.pdf';

    /**
     * Valeurs portées par le CERFA, avant impression.
     *
     * Exposées pour que le contenu du document soit vérifiable au niveau des
     * valeurs — assurer qu'un CERFA porte le SIRET du bon CFA en fouillant les
     * octets du PDF serait illisible et fragile.
     *
     * @return array<string, string|bool|null>
     */
    public function champs(Contract $contract): array
    {
        return $this->donnees($contract);
    }

    /**
     * Génère le CERFA pré-rempli et retourne le PDF (octets bruts).
     */
    public function pour(Contract $contract): string
    {
        $carte = config('cerfa_map');
        $donnees = $this->donnees($contract);

        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $nbPages = $pdf->setSourceFile(resource_path(self::TEMPLATE));

        for ($n = 1; $n <= $nbPages; $n++) {
            $tpl = $pdf->importPage($n);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage('P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            $pdf->SetTextColor(17, 24, 60); // encre bleu-noir, lisible à l'impression

            foreach ($carte as $cle => $pos) {
                if ($pos['p'] !== $n - 1) {
                    continue;
                }

                $valeur = $donnees[$cle] ?? null;

                if ($pos['check']) {
                    if (! empty($valeur)) {
                        $this->croix($pdf, $pos);
                    }

                    continue;
                }

                if ($valeur === null || $valeur === '') {
                    continue;
                }

                $this->texte($pdf, (string) $valeur, $pos);
            }
        }

        return $pdf->Output('S');
    }

    /** Pose un texte dans la case, en réduisant la police pour tenir. */
    private function texte(Fpdi $pdf, string $texte, array $pos): void
    {
        $texte = $this->enc($texte);
        $largeur = $pos['w'] - 2;
        $taille = (float) $pos['s'];

        $pdf->SetFont('Helvetica', '', $taille);
        while ($taille > 5 && $pdf->GetStringWidth($texte) > $largeur) {
            $taille -= 0.5;
            $pdf->SetFont('Helvetica', '', $taille);
        }
        // Encore trop long : on tronque proprement.
        while (strlen($texte) > 1 && $pdf->GetStringWidth($texte) > $largeur) {
            $texte = substr($texte, 0, -1);
        }

        $pdf->SetXY($pos['x'] + 1, $pos['y']);
        $pdf->Cell($pos['w'], $pos['h'], $texte, 0, 0, $pos['a']);
    }

    /** Coche une case (croix centrée). */
    private function croix(Fpdi $pdf, array $pos): void
    {
        $pdf->SetFont('Helvetica', 'B', min(10, $pos['h'] + 2));
        $pdf->SetXY($pos['x'] - 1, $pos['y'] - 1.5);
        $pdf->Cell($pos['w'] + 2, $pos['h'] + 2, 'X', 0, 0, 'C');
    }

    /**
     * Construit toutes les valeurs du CERFA à partir du contrat et des
     * modèles liés (candidat, entreprise, tuteur, formation, profil CFA).
     *
     * @return array<string, string|bool|null>
     */
    private function donnees(Contract $contract): array
    {
        $cand = $contract->candidate;
        $co = $contract->company;
        $tuteur = $contract->tuteur;
        $tuteur2 = $contract->tuteur2;
        $formation = $contract->formation ?? $cand?->formationVisee;
        $cfa = Organisation::courante();
        $principal = $co?->contactPrincipal()->first();

        $public = $co?->secteur_type === 'public';

        $naiss = $this->partsDate($cand?->date_naissance);
        $debut = $this->partsDate($contract->dateDebutEffective());
        $fin = $this->partsDate($contract->dateFinEffective());
        $conclusion = $this->partsDate($contract->date_conclusion ?? $contract->date_signature);
        $pratique = $this->partsDate($contract->date_debut_formation_pratique);
        $debutForm = $this->partsDate($contract->date_debut);
        $finEpreuves = $this->partsDate($contract->date_fin);

        // L'établissement d'exécution du contrat : le lieu d'exécution s'il est
        // renseigné, sinon le siège de l'entreprise.
        $executionRenseigne = filled($contract->lieu_execution);
        $adrEmployeur = $this->adresse(
            $executionRenseigne ? $contract->lieu_execution : $co?->adresse,
            $contract->lieu_execution_code_postal ?: $co?->code_postal,
            $contract->lieu_execution_ville ?: $co?->ville,
            $executionRenseigne ? $contract->lieu_execution_numero : $co?->numero_siege,
        );
        $adrApprenti = $this->adresse($cand?->adresse, $cand?->code_postal, $cand?->ville);
        $adrCfa = $this->adresse($cfa->adresse, $cfa->code_postal, $cfa->ville);

        // Lieu de formation distinct du CFA responsable ?
        $lieuDistinct = filled($contract->lieu_formation);
        $adrLieu = $this->adresse($contract->lieu_formation, $contract->lieu_formation_code_postal, $contract->lieu_formation_ville, $contract->lieu_formation_numero);
        $heuresDistance = (int) $contract->heures_elearning + (int) $contract->heures_classe_virtuelle;

        $d = [
            // ---- MODE CONTRACTUEL (en-tête) ----
            'mode_contractuel' => $this->codeModeContractuel($contract->mode_contractuel),

            // ---- EMPLOYEUR ----
            'employeur_prive' => ! $public,
            'employeur_public' => $public,
            'employeur_denomination' => $co?->raison_sociale,
            'employeur_siret' => $this->siret($co?->siret),
            'employeur_type' => $this->codeTypeEmployeur($co?->type_employeur),
            'employeur_specifique' => $this->codeEmployeurSpecifique($co?->type_employeur_specifique),
            'employeur_adr_num' => $adrEmployeur['num'],
            'employeur_adr_voie' => $adrEmployeur['voie'],
            'employeur_adr_complement' => $contract->lieu_execution_complement,
            'employeur_adr_cp' => $adrEmployeur['cp'],
            'employeur_adr_commune' => $adrEmployeur['commune'],
            'employeur_ape' => $co?->code_ape_naf,
            'employeur_effectif' => $co?->nombre_salaries !== null ? (string) $co->nombre_salaries : null,
            'employeur_idcc' => $co?->code_idcc,
            'employeur_tel' => $principal?->telephone,
            'employeur_courriel' => $principal?->email,
            'employeur_public_chomage' => $public && (bool) $contract->regime_assurance_chomage,

            // ---- APPRENTI ----
            'apprenti_nom_naissance' => $cand?->nom,
            'apprenti_prenom' => $cand?->prenom,
            'apprenti_naiss_jj' => $naiss['jj'],
            'apprenti_naiss_mm' => $naiss['mm'],
            'apprenti_naiss_aaaa' => $naiss['aaaa'],
            'apprenti_nir' => $this->nir($cand?->num_secu),
            'apprenti_sexe_m' => $cand?->sexe === 'M',
            'apprenti_sexe_f' => $cand?->sexe === 'F',
            'apprenti_dept_naissance' => $cand?->departement_naissance,
            'apprenti_commune_naissance' => $cand?->lieu_naissance,
            'apprenti_nationalite' => $this->codeNationalite($cand?->nationalite),
            'apprenti_regime_social' => $this->codeRegimeSocial($cand?->regime_social),
            'apprenti_situation_avant' => $this->codeSituationAvant($cand?->situation_avant_contrat),
            'apprenti_diplome_max' => $this->codeDiplome($cand?->diplome_max),
            'apprenti_dernier_diplome' => $this->codeDiplome($cand?->dernier_diplome_prepare),
            'apprenti_derniere_classe' => $this->codeDerniereClasse($cand?->derniere_classe_suivie),
            'apprenti_adr_num' => $adrApprenti['num'],
            'apprenti_adr_voie' => $adrApprenti['voie'],
            'apprenti_adr_cp' => $adrApprenti['cp'],
            'apprenti_adr_commune' => $adrApprenti['commune'],
            'apprenti_tel' => $cand?->telephone,
            'apprenti_courriel' => $cand?->email,
            'apprenti_sportif_oui' => (bool) $cand?->sportif_haut_niveau,
            'apprenti_sportif_non' => $cand !== null && ! $cand->sportif_haut_niveau,
            'apprenti_rqth_oui' => (bool) $cand?->rqth,
            'apprenti_rqth_non' => $cand !== null && ! $cand->rqth,
            'apprenti_boe_oui' => (bool) $cand?->boe,
            'apprenti_boe_non' => $cand !== null && ! $cand->boe,
            'apprenti_equiv_oui' => (bool) $cand?->aeeh_pch_pps,
            'apprenti_equiv_non' => $cand !== null && ! $cand->aeeh_pch_pps,
            'apprenti_projet_oui' => (bool) $cand?->projet_creation_entreprise,
            'apprenti_projet_non' => $cand !== null && ! $cand->projet_creation_entreprise,
            'apprenti_intitule_diplome' => $cand?->intitule_dernier_diplome,

            // ---- MAÎTRE D'APPRENTISSAGE 1 ----
            'maitre1_nom' => $tuteur?->nom,
            'maitre1_prenom' => $tuteur?->prenom,
            'maitre1_courriel' => $tuteur?->email,
            'maitre1_emploi' => $tuteur?->fonction,
            'maitre1_diplome' => $this->labelDiplome($tuteur?->diplome),
            'maitre1_niveau' => $this->codeNiveau($tuteur?->niveau_diplome),
            'employeur_atteste_maitre' => $tuteur !== null,

            // ---- CONTRAT ----
            'type_contrat_avenant' => $this->codeTypeContrat($contract->nature_contrat),
            'type_derogation' => $contract->derogation ? $this->codeDerogation($contract->type_derogation) : null,
            'date_conclusion_jj' => $conclusion['jj'],
            'date_conclusion_mm' => $conclusion['mm'],
            'date_conclusion_aaaa' => $conclusion['aaaa'],
            'date_debut_jj' => $debut['jj'],
            'date_debut_mm' => $debut['mm'],
            'date_debut_aaaa' => $debut['aaaa'],
            'date_formation_pratique_jj' => $pratique['jj'],
            'date_formation_pratique_mm' => $pratique['mm'],
            'date_formation_pratique_aaaa' => $pratique['aaaa'],
            'date_fin_jj' => $fin['jj'],
            'date_fin_mm' => $fin['mm'],
            'date_fin_aaaa' => $fin['aaaa'],
            'duree_hebdo_heures' => $contract->duree_hebdo_heures !== null ? (string) $contract->duree_hebdo_heures : null,
            'duree_hebdo_minutes' => $contract->duree_hebdo_minutes !== null ? (string) $contract->duree_hebdo_minutes : null,
            'travail_dangereux_oui' => (bool) $contract->travail_dangereux,
            'travail_dangereux_non' => $contract->travail_dangereux !== null && ! $contract->travail_dangereux,
            'caisse_retraite' => $co?->caisse_retraite,
            'avantage_autre' => $contract->autres_avantages ? ($contract->autres_avantages_detail ?: 'Oui') : null,

            // ---- FORMATION ----
            'cfa_entreprise_non' => true,
            'diplome_vise' => $formation?->libelle,
            'diplome_intitule' => $formation?->libelle,
            'code_rncp' => $contract->code_rncp ?? $formation?->code_rncp,
            'code_diplome' => $formation?->code_diplome,
            'cfa_denomination' => $cfa->designation(),
            'cfa_uai' => $cfa->numero_uai,
            'cfa_siret' => $this->siret($cfa->siret),
            'cfa_adr_num' => $adrCfa['num'],
            'cfa_adr_voie' => $adrCfa['voie'],
            'cfa_adr_cp' => $adrCfa['cp'],
            'cfa_adr_commune' => $adrCfa['commune'],
            'date_debut_cfa_jj' => $debutForm['jj'],
            'date_debut_cfa_mm' => $debutForm['mm'],
            'date_debut_cfa_aaaa' => $debutForm['aaaa'],
            'date_fin_epreuves_jj' => $finEpreuves['jj'],
            'date_fin_epreuves_mm' => $finEpreuves['mm'],
            'date_fin_epreuves_aaaa' => $finEpreuves['aaaa'],
            'duree_formation_heures' => $contract->duree_formation_heures !== null ? (string) $contract->duree_formation_heures : null,
            'heures_distance' => $heuresDistance > 0 ? (string) $heuresDistance : null,
            'cfa_lieu_principal' => ! $lieuDistinct,
            'employeur_atteste_pieces' => true,
            'fait_a' => $cfa->ville,
        ];

        // Second maître d'apprentissage (si désigné).
        if ($tuteur2 !== null) {
            $n2 = $this->partsDate($tuteur2->date_naissance);
            $d += [
                'maitre2_nom' => $tuteur2->nom,
                'maitre2_prenom' => $tuteur2->prenom,
                'maitre2_courriel' => $tuteur2->email,
                'maitre2_emploi' => $tuteur2->fonction,
                'maitre2_diplome' => $this->labelDiplome($tuteur2->diplome),
                'maitre2_niveau' => $this->codeNiveau($tuteur2->niveau_diplome),
                'maitre2_naiss_jj' => $n2['jj'],
                'maitre2_naiss_mm' => $n2['mm'],
                'maitre2_naiss_aaaa' => $n2['aaaa'],
            ];
        }

        // Date de naissance du maître 1.
        $n1 = $this->partsDate($tuteur?->date_naissance);
        $d += [
            'maitre1_naiss_jj' => $n1['jj'],
            'maitre1_naiss_mm' => $n1['mm'],
            'maitre1_naiss_aaaa' => $n1['aaaa'],
        ];

        // Avantages en nature (nourriture / logement) — parties euros / centimes.
        $this->montantEuros($d, 'avantage_repas', $contract->avantage_repas);
        $this->montantEuros($d, 'avantage_logement', $contract->avantage_logement);
        $this->montantEuros($d, 'salaire_brut', $contract->salaire_mensuel_brut);

        // Lieu de formation distinct du CFA responsable.
        if ($lieuDistinct) {
            $d += [
                'lieu_formation_denomination' => $contract->lieu_formation_denomination,
                'lieu_formation_uai' => $contract->lieu_formation_uai,
                'lieu_formation_siret' => $this->siret($contract->lieu_formation_siret),
                'lieu_formation_num' => $adrLieu['num'],
                'lieu_formation_voie' => $adrLieu['voie'] ?? $contract->lieu_formation,
                'lieu_formation_complement' => $contract->lieu_formation_complement,
                'lieu_formation_cp' => $adrLieu['cp'] ?: $contract->lieu_formation_code_postal,
                'lieu_formation_commune' => $adrLieu['commune'] ?: $contract->lieu_formation_ville,
            ];
        }

        // Représentant légal de l'apprenti (mineur non émancipé) : rempli dès
        // qu'un nom est renseigné.
        if (filled($cand?->repr_legal_nom)) {
            $adrRepr = $this->adresse($cand->repr_legal_adresse, $cand->repr_legal_code_postal, $cand->repr_legal_ville);
            $d += [
                'repres_nom_prenom' => trim($cand->repr_legal_nom.' '.$cand->repr_legal_prenom),
                'repres_adr_num' => $adrRepr['num'],
                'repres_adr_voie' => $adrRepr['voie'] ?? $cand->repr_legal_adresse,
                'repres_adr_complement' => $cand->repr_legal_complement,
                'repres_adr_cp' => $adrRepr['cp'] ?: $cand->repr_legal_code_postal,
                'repres_adr_commune' => $adrRepr['commune'] ?: $cand->repr_legal_ville,
                'repres_courriel' => $cand->repr_legal_email,
            ];
        }

        return $d + $this->remuneration($contract, $cand?->date_naissance);
    }

    /**
     * Grille de rémunération légale : jusqu'à 2 périodes par année d'exécution
     * (changement de tranche d'âge en cours d'année → 2 colonnes du/au/% dans
     * le CERFA), calculée par {@see RemunerationApprenti}.
     *
     * @return array<string, string>
     */
    private function remuneration(Contract $contract, mixed $dateNaissance): array
    {
        $periodes = RemunerationApprenti::periodes(
            $dateNaissance, $contract->dateDebutEffective(), $contract->dateFinEffective(),
        );

        if ($periodes === []) {
            return [];
        }

        $base = $contract->smc ? 'SMC' : 'SMIC';

        $parAnnee = [];
        foreach ($periodes as $p) {
            $parAnnee[$p['annee']][] = $p;
        }

        $out = [];
        foreach ([1, 2, 3, 4] as $n) {
            if (! isset($parAnnee[$n])) {
                continue;
            }

            // Au plus 2 colonnes par année dans le CERFA (slots « a » et « b »).
            foreach (['a', 'b'] as $k => $slot) {
                if (! isset($parAnnee[$n][$k])) {
                    continue;
                }

                $p = $parAnnee[$n][$k];
                $du = $this->partsDate($p['du']);
                $au = $this->partsDate($p['au']);

                $out["rem{$n}{$slot}_du_jj"] = $du['jj'];
                $out["rem{$n}{$slot}_du_mm"] = $du['mm'];
                $out["rem{$n}{$slot}_du_aaaa"] = $du['aaaa'];
                $out["rem{$n}{$slot}_au_jj"] = $au['jj'];
                $out["rem{$n}{$slot}_au_mm"] = $au['mm'];
                $out["rem{$n}{$slot}_au_aaaa"] = $au['aaaa'];
                $out["rem{$n}{$slot}_pct"] = (string) $p['taux'];
                $out["rem{$n}{$slot}_base"] = $base;
            }
        }

        return $out;
    }

    /** Décompose un montant en parties euros (droite) / centimes (gauche). */
    private function montantEuros(array &$d, string $prefixe, mixed $montant): void
    {
        if (blank($montant)) {
            return;
        }

        $formate = number_format((float) $montant, 2, '.', '');
        [$euros, $cents] = explode('.', $formate);
        $d[$prefixe.'_euros'] = $euros;
        $d[$prefixe.'_cents'] = $cents;
    }

    /** Code « type d'employeur » (notice CERFA) depuis notre nomenclature. */
    private function codeTypeEmployeur(?string $type): ?string
    {
        return match ($type) {
            'entreprise_rm' => '11',
            'entreprise_rcs' => '12',
            'employeur_msa' => '13',
            'profession_liberale' => '14',
            'association' => '15',
            'autre_prive' => '16',
            'etat' => '21',
            'commune' => '22',
            'departement' => '23',
            'region' => '24',
            'etablissement_public_hospitalier' => '25',
            'etablissement_public_enseignement' => '26',
            'etablissement_public_administratif_etat' => '27',
            'etablissement_public_administratif_local' => '28',
            'autre_public' => '29',
            'etablissement_public_ic' => '30',
            default => null,
        };
    }

    /** Code « mode contractuel de l'apprentissage » (notice CERFA, page 1). */
    private function codeModeContractuel(?string $mode): ?string
    {
        return match ($mode) {
            'duree_limitee' => '1',
            'cdi' => '2',
            'travail_temporaire' => '3',
            'saisonnier_deux_employeurs' => '4',
            default => null,
        };
    }

    /** Code « employeur spécifique » (notice CERFA n°51649#09, page 2). */
    private function codeEmployeurSpecifique(?string $type): ?string
    {
        return match ($type) {
            'travail_temporaire' => '1',
            'groupement_employeurs' => '2',
            'saisonnier' => '3',
            'apprentissage_familial' => '4',
            'aucun' => '0',
            default => null,
        };
    }

    /** Code « régime social » de l'apprenti (notice CERFA : 1 MSA / 2 URSSAF). */
    private function codeRegimeSocial(?string $regime): ?string
    {
        return match ($regime) {
            'msa' => '1',
            'general' => '2',
            default => null,
        };
    }

    /** Code « situation avant contrat » (notice CERFA n°51649#09, pages 2-3). */
    private function codeSituationAvant(?string $situation): ?string
    {
        return match ($situation) {
            'scolaire' => '1',
            'prepa_apprentissage' => '2',
            'etudiant' => '3',
            'apprentissage' => '4',
            'professionnalisation' => '5',
            'contrat_aide' => '6',
            'stagiaire_avant_apprentissage' => '7',
            'stagiaire_apres_rupture' => '8',
            'stagiaire_autre' => '9',
            'salarie' => '10',
            'recherche_emploi' => '11',
            'inactif' => '12',
            default => null,
        };
    }

    /**
     * Code « diplôme ou titre » (le plus élevé obtenu, dernier préparé ou visé)
     * depuis la table « diplômes et titres de l'apprenti » de la notice
     * officielle CERFA n°51649#09 (page 3).
     */
    private function codeDiplome(?string $key): ?string
    {
        return match ($key) {
            'doctorat' => '80',
            'master' => '73',
            'diplome_ingenieur' => '75',
            'ecole_commerce' => '76',
            'autre_bac5' => '79',
            'licence_pro' => '62',
            'licence_generale' => '63',
            'but' => '64',
            'autre_bac34' => '69',
            'bts' => '54',
            'dut' => '55',
            'autre_bac2' => '58',
            'bac_pro' => '41',
            'bac_general' => '42',
            'bac_techno' => '43',
            'dsp' => '44',
            'autre_bac' => '49',
            'cap' => '33',
            'bep' => '34',
            'certificat_specialisation' => '35',
            'autre_cap_bep' => '38',
            'brevet' => '25',
            'cfg' => '26',
            'aucun' => '13',
            default => null,
        };
    }

    /** Code « dernière classe ou année suivie » (notice CERFA, page 3). */
    private function codeDerniereClasse(?string $classe): ?string
    {
        return match ($classe) {
            'derniere_annee_diplome' => '01',
            'cycle_1_validee' => '11',
            'cycle_1_non_validee' => '12',
            'cycle_2_validee' => '21',
            'cycle_2_non_validee' => '22',
            'cycle_3_validee' => '31',
            'cycle_3_non_validee' => '32',
            'premier_cycle_college' => '40',
            'interrompu_3eme' => '41',
            'interrompu_4eme' => '42',
            default => null,
        };
    }

    /** Code « type de contrat ou d'avenant » (notice CERFA, pages 5-6). */
    private function codeTypeContrat(?string $nature): ?string
    {
        return match ($nature) {
            'premier_contrat' => '11',
            'succession_meme_employeur' => '21',
            'succession_autre_employeur' => '22',
            'succession_apres_rupture' => '23',
            'avenant_situation_juridique' => '31',
            'avenant_changement_employeur_saisonnier' => '32',
            'avenant_prolongation_echec' => '33',
            'avenant_prolongation_rqth' => '34',
            'avenant_diplome_supplementaire' => '35',
            'avenant_autres' => '36',
            'avenant_lieu_execution' => '37',
            'avenant_lieu_formation' => '38',
            default => null,
        };
    }

    /** Code « type de dérogation » (notice CERFA, page 6). */
    private function codeDerogation(?string $type): ?string
    {
        return match ($type) {
            'age_inf_16' => '11',
            'age_sup_29' => '12',
            'reduction_duree' => '21',
            'allongement_duree' => '22',
            'cumul' => '50',
            'autre' => '60',
            default => null,
        };
    }

    /** Code nationalité (notice CERFA) : 1 française, 2 UE, 3 hors UE. */
    private function codeNationalite(?string $nationalite): ?string
    {
        return match ($nationalite) {
            'francaise' => '1',
            'ue' => '2',
            'hors_ue' => '3',
            default => null,
        };
    }

    /** Niveau de diplôme (cadre européen des certifications) depuis notre nomenclature. */
    private function codeNiveau(?string $niveau): ?string
    {
        return match ($niveau) {
            'bac_5_plus' => '7',
            'bac_3_4' => '6',
            'bac_2' => '5',
            'bac' => '4',
            'cap_bep' => '3',
            default => null,
        };
    }

    /** Libellé lisible d'un diplôme (nomenclature CERFA groupée par niveau), ou null. */
    private function labelDiplome(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        // La nomenclature « diplome » est regroupée par niveau (optgroups) :
        // on aplatit pour retrouver le libellé quel que soit le groupe.
        foreach (config('cerfa_options.diplome') as $groupe) {
            if (isset($groupe[$key])) {
                return $groupe[$key];
            }
        }

        return null;
    }

    /** NIR (n° de sécurité sociale) espacé pour la lisibilité, ou null. */
    private function nir(?string $nir): ?string
    {
        $chiffres = preg_replace('/\s/', '', (string) $nir);

        return $chiffres !== '' ? $chiffres : null;
    }

    /**
     * Décompose une adresse en numéro / voie / code postal / commune, pour
     * remplir les cases dédiées du CERFA. Les champs code postal / ville
     * dédiés priment ; sinon on extrait le code postal (5 chiffres) et la
     * commune de la chaîne complète (« 12 rue X, 75001 Paris »). Le numéro
     * de voirie en tête (« 69 chemin Mallet », « 12 bis rue X ») est
     * toujours séparé de la voie.
     *
     * Quand le numéro de voirie est stocké dans sa propre colonne (`$numero`),
     * il prime : la voie saisie ne le contient alors pas. Le repli par
     * extraction reste nécessaire pour les adresses historiques, saisies en
     * un seul champ avant la séparation numéro / voie.
     *
     * @return array{num: ?string, voie: ?string, cp: ?string, commune: ?string}
     */
    private function adresse(?string $complet, ?string $cp, ?string $ville, ?string $numero = null): array
    {
        $decoupe = function (?string $voie) use ($numero): array {
            if (blank($numero)) {
                return $this->separerNumero($voie);
            }

            $numero = trim($numero);

            // Les fiches saisies avant la séparation numéro / voie répètent
            // souvent le numéro en tête de la voie (« 228 » + « 228 rue X ») :
            // sans ce retrait, il s'imprimerait deux fois sur le CERFA.
            $extrait = $this->separerNumero($voie);
            $voie = $extrait['num'] === $numero ? $extrait['voie'] : $voie;

            return ['num' => $numero, 'voie' => blank($voie) ? null : trim($voie, ' ,')];
        };

        if (filled($cp) || filled($ville)) {
            return $decoupe($complet) + ['cp' => $cp, 'commune' => $ville];
        }

        if (filled($complet) && preg_match('/^(.*?)[,\s]+(\d{5})\s+(.+)$/', trim($complet), $m)) {
            return $decoupe(trim($m[1], ' ,')) + ['cp' => $m[2], 'commune' => trim($m[3])];
        }

        return $decoupe($complet) + ['cp' => null, 'commune' => null];
    }

    /**
     * Sépare le numéro de voirie en tête de la voie : « 69 chemin Mallet »
     * → num « 69 », voie « chemin Mallet ». Gère bis / ter / quater.
     *
     * @return array{num: ?string, voie: ?string}
     */
    private function separerNumero(?string $voie): array
    {
        if (blank($voie)) {
            return ['num' => null, 'voie' => null];
        }

        // Numéro en tête (+ éventuel B / bis / ter / quater), suivi d'un
        // séparateur (espace ou virgule) puis du nom de voie.
        if (preg_match('/^\s*(\d+[a-dA-D]?(?:\s*(?:bis|ter|quater))?)[\s,]+(.+)$/iu', trim($voie), $m)) {
            return ['num' => trim($m[1]), 'voie' => trim($m[2], ' ,')];
        }

        return ['num' => null, 'voie' => trim($voie, ' ,')];
    }

    /** Découpe une date en jj / mm / aaaa (chaînes vides si absente). */
    private function partsDate(mixed $date): array
    {
        if (blank($date)) {
            return ['jj' => '', 'mm' => '', 'aaaa' => ''];
        }

        try {
            $c = $date instanceof Carbon ? $date : Carbon::parse($date);
        } catch (\Throwable) {
            return ['jj' => '', 'mm' => '', 'aaaa' => ''];
        }

        return ['jj' => $c->format('d'), 'mm' => $c->format('m'), 'aaaa' => $c->format('Y')];
    }

    /** Formate un SIRET « 123 456 789 00012 » (regroupé pour la lisibilité). */
    private function siret(?string $siret): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $siret);

        if (strlen($chiffres) !== 14) {
            return $siret ?: null;
        }

        return substr($chiffres, 0, 3).' '.substr($chiffres, 3, 3).' '
            .substr($chiffres, 6, 3).' '.substr($chiffres, 9, 5);
    }

    /** Convertit l'UTF-8 vers l'encodage des polices coeur FPDF (Windows-1252). */
    private function enc(string $texte): string
    {
        $converti = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texte);

        return $converti !== false ? $converti : $texte;
    }
}
