<?php

namespace App\Documents;

use App\Enums\NeedOrigine;
use App\Models\Need;
use App\Models\Organisation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Génère la « Fiche besoin » d'une offre : un document préparatoire rédigé qui
 * analyse le besoin exprimé par une entreprise, prêt à imprimer et à archiver.
 *
 * Format calqué sur le modèle fourni par le CFA (« FICHE BESOIN — ALTERNANCE /
 * APPRENTISSAGE »), en six sections : informations générales, contexte de
 * l'entreprise, besoin opérationnel, compétences attendues, justification du
 * choix de la formation, conclusion. Sans logo ni bas de page, à sa demande.
 *
 * Les sections rédigées (contexte, besoin, justification, conclusion) sont
 * produites de façon DÉTERMINISTE à partir des seules données de l'offre —
 * aucune IA, aucun texte inventé. Une donnée absente est annoncée comme telle
 * (« à compléter », « à préciser »), jamais comblée par une supposition ; et la
 * conclusion ne « constate » l'adéquation que pour un besoin réellement endossé
 * par le CFA — sinon elle reste au conditionnel (cf. estEndosse()).
 *
 * Le document sert aussi de preuve à l'indicateur n°4 du Référentiel National
 * Qualité (« Le prestataire analyse le besoin du bénéficiaire en lien avec
 * l'entreprise ») : c'est FicheBesoinService qui le rattache à cet indicateur.
 */
class FicheBesoin
{
    /** Génère la fiche besoin et retourne le PDF (octets bruts). */
    public function pour(Need $need): string
    {
        return Pdf::loadView('pdf.fiche-besoin', [
            'd' => $this->donnees($need),
        ])->setPaper('a4')->output();
    }

    /**
     * Nom de fichier stable et lisible : « fiche-besoin-BES-2026-0019.pdf ».
     * Sert au téléchargement comme au nom en GED.
     */
    public function nomFichier(Need $need): string
    {
        return 'fiche-besoin-'.$this->reference($need).'.pdf';
    }

    /**
     * Référence de la fiche, dérivée de l'offre : « BES-2026-0019 ».
     * Année de création de l'offre + identifiant, donc jamais deux fois la même.
     */
    public function reference(Need $need): string
    {
        $annee = ($need->created_at ?? now())->format('Y');

        return 'BES-'.$annee.'-'.str_pad((string) $need->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Toutes les valeurs de la fiche, aplaties en scalaires pour la vue.
     * Les champs absents restent nuls — la vue s'en charge.
     *
     * @return array<string, mixed>
     */
    public function donnees(Need $need): array
    {
        $need->loadMissing(['company.opco', 'formation', 'tuteur']);

        $co = $need->company;
        $tuteur = $need->tuteur;
        $formation = $need->formation;
        $cfa = Organisation::courante();

        $cfaNom = $cfa?->designation() ?: $cfa?->nom;
        $formationLibelle = $formation?->rncp_intitule ?: $formation?->libelle;
        $adresse = $this->adresse($co?->adresse, $co?->code_postal, $co?->ville);

        return [
            // ---- En-tête ----
            'cfa_nom' => $cfaNom,
            'sous_titre' => 'Document préparatoire établi par le CFA'
                .($cfaNom ? ' '.$cfaNom : '')
                .' préalablement à l\'entrée en formation.',

            // ---- 1 · Informations générales (table) ----
            'entreprise' => $co?->raison_sociale,
            'entreprise_siret' => $this->siret($co?->siret),
            'formation' => $formationLibelle,
            'code_rncp' => $formation?->code_rncp,
            'poste' => $need->intitule_poste,
            'maitre_apprentissage' => $this->personne($tuteur?->prenom, $tuteur?->nom, $tuteur?->fonction),
            'date_fiche' => $this->moisAnnee($need->created_at),

            // ---- 2 · Contexte de l'entreprise (prose générée) ----
            'contexte' => $this->contexte($co?->raison_sociale, $co?->secteur, $adresse),

            // ---- 3 · Besoin opérationnel (prose : les mots de l'entreprise, sinon repli) ----
            'besoin' => $this->besoinOperationnel($need->prerequis, $need->intitule_poste, $need->nb_postes),

            // ---- 4 · Compétences attendues (référentiel de la formation) ----
            'competences' => $this->competences($formation?->matieres, $need->intitule_poste),

            // ---- 5 · Justification du choix de la formation (prose générée) ----
            'justification' => $this->justification($co?->raison_sociale, $need->intitule_poste, $formationLibelle, $formation?->code_rncp),

            // ---- 6 · Conclusion (prose générée, prudente tant que le besoin n'est pas endossé) ----
            'conclusion' => $this->conclusion(
                $co?->raison_sociale,
                $cfaNom,
                $formationLibelle,
                $formation?->code_rncp,
                $this->estEndosse($need),
            ),
        ];
    }

    /**
     * Le CFA a-t-il endossé ce besoin ? Seul cas où la conclusion affirme la
     * cohérence : un dépôt d'entreprise validé, ou un besoin saisi par le CFA
     * lui-même et toujours en cours. Un besoin en attente OU rejeté reste au
     * conditionnel — on n'affirme pas une adéquation que personne n'a relue,
     * et surtout pas pour une offre écartée.
     */
    private function estEndosse(Need $need): bool
    {
        if ($need->validee_at !== null) {
            return true;
        }

        return $need->origine === NeedOrigine::Interne && ! $need->estCloture();
    }

    /* ----------------------------------------------------------------
     |  Prose générée — déterministe, à partir des seules données de
     |  l'offre. Aucune IA, aucun texte inventé : les données absentes
     |  sont annoncées comme telles, jamais comblées par une supposition.
     * ---------------------------------------------------------------- */

    /** § 2 — Contexte : secteur et implantation de l'entreprise. */
    private function contexte(?string $entreprise, ?string $secteur, ?string $adresse): string
    {
        $nom = $entreprise ?: 'L\'entreprise d\'accueil';

        return match (true) {
            filled($secteur) && filled($adresse) => "{$nom} exerce une activité relevant du secteur : {$secteur}, depuis son établissement situé {$adresse}.",
            filled($secteur) => "{$nom} exerce une activité relevant du secteur : {$secteur}.",
            filled($adresse) => "{$nom} accueille l'alternant depuis son établissement situé {$adresse}.",
            default => "{$nom} accueille l'alternant dans le cadre de son activité.",
        };
    }

    /** § 3 — Besoin opérationnel : les mots de l'entreprise en priorité. */
    private function besoinOperationnel(?string $prerequis, ?string $poste, ?int $nbPostes): string
    {
        if (filled($prerequis)) {
            return trim($prerequis);
        }

        $intitule = filled($poste) ? "de {$poste}" : 'proposé';
        $pluriel = ($nbPostes ?? 1) > 1 ? " ({$nbPostes} postes)" : '';

        return "L'entreprise exprime un besoin d'appui opérationnel sur le poste {$intitule}{$pluriel}, "
            .'dans le cadre d\'un recrutement en alternance. Les missions précises seront affinées avec l\'entreprise lors de la qualification du besoin.';
    }

    /**
     * § 4 — Compétences attendues : le référentiel de la formation visée.
     * À défaut de référentiel connu, une seule ligne renvoyant au poste.
     *
     * @return list<string>
     */
    private function competences(mixed $matieres, ?string $poste): array
    {
        if (is_array($matieres) && $matieres !== []) {
            return array_values(array_filter(array_map('trim', $matieres), 'strlen'));
        }

        $intitule = filled($poste) ? " au poste de {$poste}" : '';

        return ["Compétences liées{$intitule} et au référentiel de la formation visée (à préciser)."];
    }

    /** § 5 — Justification : cohérence poste ↔ formation. */
    private function justification(?string $entreprise, ?string $poste, ?string $formation, ?string $rncp): string
    {
        $nom = $entreprise ?: 'l\'entreprise';
        // « la formation X » si connue, sinon « la formation visée » — sans doubler le mot.
        $laFormation = filled($formation) ? "la formation {$formation}" : 'la formation visée';
        $intitulePoste = $poste ?: 'le poste proposé';
        $ref = filled($rncp) ? " ({$rncp})" : '';

        return ucfirst($laFormation)."{$ref} prépare aux compétences mobilisées sur le poste {$intitulePoste}. "
            ."Les missions confiées par {$nom} recoupent le référentiel de cette certification. "
            .'Le besoin exprimé par l\'entreprise justifie ainsi l\'adéquation entre le poste proposé et la formation visée.';
    }

    /**
     * § 6 — Conclusion. Tant que le besoin n'est pas endossé par le CFA, la
     * conclusion reste au conditionnel : on n'affirme pas une adéquation que
     * personne n'a relue. Une fois endossé, elle est constatée.
     */
    private function conclusion(?string $entreprise, ?string $cfa, ?string $formation, ?string $rncp, bool $endosse): string
    {
        $nom = $entreprise ?: 'l\'entreprise';
        $organisme = $this->organisme($cfa);
        $laFormation = filled($formation) ? "la formation {$formation}" : 'la formation visée';
        $ref = filled($rncp) ? " — {$rncp}" : '';

        if ($endosse) {
            return "Après analyse du besoin exprimé par {$nom}, {$organisme} constate une cohérence entre les missions "
                ."confiées à l'alternant et les compétences visées par {$laFormation}{$ref}. "
                .'Le besoin est considéré comme compatible avec le parcours de formation proposé.';
        }

        return "Sous réserve de sa validation par {$organisme}, le besoin exprimé par {$nom} apparaît cohérent avec les "
            ."compétences visées par {$laFormation}{$ref}, et compatible avec le parcours de formation proposé.";
    }

    /**
     * Désignation du CFA introduite par son article, sans doubler « CFA » quand
     * le nom le contient déjà : « le CFA ACTION CBM », mais « le CFA » si
     * l'organisation s'appelle littéralement « CFA ».
     */
    private function organisme(?string $cfa): string
    {
        if (blank($cfa)) {
            return 'le CFA';
        }

        return preg_match('/^cfa\b/i', trim($cfa)) ? "le {$cfa}" : "le CFA {$cfa}";
    }

    /** Mois et année en toutes lettres, capitalisés : « Décembre 2024 ». */
    private function moisAnnee(mixed $date): string
    {
        $c = $date instanceof Carbon ? $date : now();

        return ucfirst($c->translatedFormat('F Y'));
    }

    /** Assemble « Prénom NOM (fonction) » en ignorant les parties absentes. */
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
}
