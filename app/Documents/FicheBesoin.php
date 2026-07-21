<?php

namespace App\Documents;

use App\Enums\NeedOrigine;
use App\Models\Need;
use App\Models\Organisation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Génère la « Fiche besoin » d'une offre : l'analyse du besoin exprimé par une
 * entreprise, prête à imprimer, à faire circuler et à archiver.
 *
 * Il n'existe aucun modèle officiel de fiche besoin (ni État, ni OPCO, ni France
 * Compétences) : chaque CFA fabrique le sien. Le gabarit reproduit ici est donc
 * bâti sur le seul cadre opposable, l'indicateur n°4 du Référentiel National
 * Qualité (Qualiopi) — « Le prestataire analyse le besoin du bénéficiaire en
 * lien avec l'entreprise et/ou le(s) financeur(s) concerné(s) ».
 *
 * Ce que l'auditeur y cherche, et que la section 5 matérialise :
 *   1. l'analyse est menée EN AMONT de la contractualisation ;
 *   2. elle peut être complétée en début de parcours ;
 *   3. elle VÉRIFIE l'adéquation des missions proposées avec la certification visée.
 *
 * Comme la convention de formation, les champs absents ne sont jamais inventés :
 * la vue imprime des pointillés à compléter à la main. Une entreprise qui dépose
 * son besoin en ligne laisse souvent la formation ou le rythme en blanc — c'est
 * précisément ce que le commercial doit qualifier.
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
        $need->loadMissing(['company.opco', 'formation', 'contact', 'tuteur']);

        $co = $need->company;
        $contact = $need->contact;
        $tuteur = $need->tuteur;
        $formation = $need->formation;
        $cfa = Organisation::courante();

        return [
            // ---- En-tête : CFA + référence ----
            'cfa_designation' => $cfa?->designation(),
            'cfa_adresse' => $this->adresse($cfa?->adresse, $cfa?->code_postal, $cfa?->ville),
            'cfa_siret' => $this->siret($cfa?->siret),
            'cfa_nda' => $cfa?->nda,
            'cfa_nom' => $cfa?->nom,
            'reference' => $this->reference($need),
            'edite_le' => now()->format('d/m/Y'),

            // ---- 1 · Entreprise ----
            'entreprise' => $co?->raison_sociale,
            'entreprise_siret' => $this->siret($co?->siret),
            'entreprise_secteur' => $co?->secteur,
            'entreprise_adresse' => $this->adresse($co?->adresse, $co?->code_postal, $co?->ville),
            'entreprise_opco' => $co?->opco?->nom,

            // ---- 2 · Interlocuteur ----
            'contact_identite' => $this->personne($contact?->prenom, $contact?->nom, $contact?->fonction),
            'contact_email' => $contact?->email,
            'contact_tel' => $contact?->telephone,
            'tuteur_identite' => $this->personne($tuteur?->prenom, $tuteur?->nom, $tuteur?->fonction),

            // ---- 3 · Poste recherché ----
            'poste' => $need->intitule_poste,
            'nb_postes' => $need->nb_postes,
            'formation' => $formation?->libelle,
            'date_demarrage' => $this->dateFr($need->date_demarrage),
            'rythme' => $need->rythme ?? $formation?->rythme_defaut,
            'lieu' => $need->localisation,

            // ---- 4 · Missions et prérequis ----
            'prerequis' => $need->prerequis,

            // ---- 5 · Analyse du besoin (Qualiopi n°4) ----
            // La certification visée est le pivot de l'indicateur : sans elle,
            // l'adéquation des missions ne peut pas être établie.
            'certification' => $formation?->rncp_intitule ?? $formation?->libelle,
            'certification_rncp' => $formation?->code_rncp,
            'certification_niveau' => $formation?->rncp_niveau ?? $formation?->niveau,

            // ---- 6 · Suivi CFA ----
            'origine' => $need->origine === NeedOrigine::Entreprise
                ? 'Déposée par l\'entreprise'
                : 'Saisie par le CFA',
            'depose_le' => $this->dateFr($need->created_at),
            'statut' => $need->statut?->getLabel(),
            'validee_le' => $this->dateFr($need->validee_at),
            'attend_validation' => $need->attendValidation(),
            'nb_candidats' => $need->matchings()->count(),
        ];
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
}
