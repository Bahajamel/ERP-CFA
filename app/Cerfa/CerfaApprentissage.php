<?php

namespace App\Cerfa;

use App\Models\CfaProfile;
use App\Models\Contract;
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
        $formation = $contract->formation ?? $cand?->formationVisee;
        $cfa = CfaProfile::current();
        $principal = $co?->contactPrincipal()->first();

        $naiss = $this->partsDate($cand?->date_naissance);
        $debut = $this->partsDate($contract->date_debut);
        $fin = $this->partsDate($contract->date_fin);

        $adrEmployeur = $this->adresse($co?->adresse, $co?->code_postal, $co?->ville);
        $adrApprenti = $this->adresse($cand?->adresse, $cand?->code_postal, $cand?->ville);

        $adrCfa = $this->adresse($cfa->adresse, $cfa->code_postal, $cfa->ville);

        $d = [
            // EMPLOYEUR
            'employeur_type_prive' => true,
            'employeur_denomination' => $co?->raison_sociale,
            'employeur_siret' => $this->siret($co?->siret),
            // Type d'employeur (notice CERFA) : 12 = entreprise inscrite au RCS,
            // cas de la très grande majorité des entreprises privées.
            'employeur_type' => '12',
            'employeur_adr_num' => $adrEmployeur['num'],
            'employeur_adr_voie' => $adrEmployeur['voie'],
            'employeur_adr_cp' => $adrEmployeur['cp'],
            'employeur_adr_commune' => $adrEmployeur['commune'],
            'employeur_tel' => $principal?->telephone,
            'employeur_courriel' => $principal?->email,

            // APPRENTI
            'apprenti_nom' => $cand?->nom,
            'apprenti_prenom' => $cand?->prenom,
            'apprenti_naiss_jj' => $naiss['jj'],
            'apprenti_naiss_mm' => $naiss['mm'],
            'apprenti_naiss_aaaa' => $naiss['aaaa'],
            'apprenti_adr_num' => $adrApprenti['num'],
            'apprenti_adr_voie' => $adrApprenti['voie'],
            'apprenti_adr_cp' => $adrApprenti['cp'],
            'apprenti_adr_commune' => $adrApprenti['commune'],
            'apprenti_tel' => $cand?->telephone,
            'apprenti_courriel' => $cand?->email,

            // MAÎTRE D'APPRENTISSAGE 1 (le tuteur)
            'maitre1_nom' => $tuteur?->nom,
            'maitre1_prenom' => $tuteur?->prenom,
            'maitre1_courriel' => $tuteur?->email,
            'maitre1_emploi' => $tuteur?->fonction,

            // CONTRAT
            'date_debut_jj' => $debut['jj'],
            'date_debut_mm' => $debut['mm'],
            'date_debut_aaaa' => $debut['aaaa'],
            'date_fin_jj' => $fin['jj'],
            'date_fin_mm' => $fin['mm'],
            'date_fin_aaaa' => $fin['aaaa'],
            'duree_hebdo_heures' => '35',

            // FORMATION
            'cfa_entreprise_non' => true,
            'formation_intitule' => $formation?->libelle,
            'code_rncp' => $contract->code_rncp ?? $formation?->code_rncp,
            'cfa_denomination' => $cfa->raison_sociale ?: $cfa->nom,
            'cfa_uai' => $cfa->numero_uai,
            'cfa_siret' => $this->siret($cfa->siret),
            'cfa_adr_num' => $adrCfa['num'],
            'cfa_adr_voie' => $adrCfa['voie'],
            'cfa_adr_cp' => $adrCfa['cp'],
            'cfa_adr_commune' => $adrCfa['commune'],
            'fait_a' => $cfa->ville,
        ];

        // Salaire brut mensuel à l'embauche (parties euros / centimes).
        if (filled($contract->salaire_mensuel_brut)) {
            $montant = number_format((float) $contract->salaire_mensuel_brut, 2, '.', '');
            [$euros, $cents] = explode('.', $montant);
            $d['salaire_brut_euros'] = $euros;
            $d['salaire_brut_cents'] = $cents;
        }

        return $d + $this->remuneration($contract, $cand?->date_naissance);
    }

    /**
     * Grille de rémunération légale : une ligne par année d'exécution
     * (du / au / % du SMIC), calculée par {@see RemunerationApprenti}.
     *
     * @return array<string, string>
     */
    private function remuneration(Contract $contract, mixed $dateNaissance): array
    {
        $periodes = RemunerationApprenti::periodes($dateNaissance, $contract->date_debut, $contract->date_fin);

        if ($periodes === []) {
            return [];
        }

        $parAnnee = [];
        foreach ($periodes as $p) {
            $parAnnee[$p['annee']][] = $p;
        }

        $out = [];
        foreach ([1, 2, 3, 4] as $n) {
            if (! isset($parAnnee[$n])) {
                continue;
            }

            $lignes = $parAnnee[$n];
            $du = $this->partsDate($lignes[0]['du']);
            $au = $this->partsDate(end($lignes)['au']);

            $out["rem{$n}_du_jj"] = $du['jj'];
            $out["rem{$n}_du_mm"] = $du['mm'];
            $out["rem{$n}_du_aaaa"] = $du['aaaa'];
            $out["rem{$n}_au_jj"] = $au['jj'];
            $out["rem{$n}_au_mm"] = $au['mm'];
            $out["rem{$n}_au_aaaa"] = $au['aaaa'];
            $out["rem{$n}_pct"] = (string) $lignes[0]['taux'];
            $out["rem{$n}_base"] = 'SMIC';
        }

        return $out;
    }

    /**
     * Décompose une adresse en numéro / voie / code postal / commune, pour
     * remplir les cases dédiées du CERFA. Les champs code postal / ville
     * dédiés priment ; sinon on extrait le code postal (5 chiffres) et la
     * commune de la chaîne complète (« 12 rue X, 75001 Paris »). Le numéro
     * de voirie en tête (« 69 chemin Mallet », « 12 bis rue X ») est
     * toujours séparé de la voie.
     *
     * @return array{num: ?string, voie: ?string, cp: ?string, commune: ?string}
     */
    private function adresse(?string $complet, ?string $cp, ?string $ville): array
    {
        if (filled($cp) || filled($ville)) {
            return $this->separerNumero($complet) + ['cp' => $cp, 'commune' => $ville];
        }

        if (filled($complet) && preg_match('/^(.*?)[,\s]+(\d{5})\s+(.+)$/', trim($complet), $m)) {
            return $this->separerNumero(trim($m[1], ' ,')) + ['cp' => $m[2], 'commune' => trim($m[3])];
        }

        return $this->separerNumero($complet) + ['cp' => null, 'commune' => null];
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
            return ['num' => trim($m[1]), 'voie' => trim($m[2], " ,")];
        }

        return ['num' => null, 'voie' => trim($voie, " ,")];
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
