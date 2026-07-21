<?php

namespace App\Services;

use App\Models\Contract;

/**
 * Complétude du dossier contrat par onglet (« tour de contrôle », phase 2).
 *
 * Chaque onglet a une liste de vérifications (champ requis pour le CERFA / la
 * convention ou le suivi). Le service renvoie, par onglet, un score 0-100 et la
 * liste des informations encore manquantes — sans jamais bloquer la saisie.
 *
 * Complète {@see ContractDocumentService} (qui, lui, suit l'état des DOCUMENTS) :
 * ici on suit l'état des DONNÉES saisies.
 */
class DossierCompletion
{
    /**
     * Synthèse : un bloc par onglet + un score global (moyenne pondérée par le
     * nombre de vérifications).
     *
     * @return array{global:int, sections:array<int, array{key:string,label:string,score:int,total:int,remplis:int,manquants:list<string>}>}
     */
    public function pour(Contract $contract): array
    {
        // Une section par onglet ÉDITABLE, dans l'ordre des onglets. L'onglet
        // « Calendrier » est en lecture seule (aucune saisie propre : ses dates
        // sont renseignées dans Étudiant / Contrat) : il n'est donc pas scoré,
        // pour ne pas compter deux fois les mêmes dates dans le global.
        $sections = [
            $this->section('etudiant', 'Étudiant', $this->etudiant($contract)),
            $this->section('entreprise', 'Entreprise', $this->entreprise($contract)),
            $this->section('contrat', 'Contrat', $this->contrat($contract)),
            $this->section('gestion', 'Gestion', $this->gestion($contract)),
        ];

        $total = array_sum(array_column($sections, 'total'));
        $remplis = array_sum(array_column($sections, 'remplis'));

        return [
            'global' => $total > 0 ? (int) round($remplis / $total * 100) : 0,
            'global_remplis' => $remplis,
            'global_total' => $total,
            'sections' => $sections,
        ];
    }

    /** Score d'un seul onglet (0-100), ou null si l'onglet n'est pas suivi. */
    public function scoreOnglet(Contract $contract, string $key): ?int
    {
        foreach ($this->pour($contract)['sections'] as $section) {
            if ($section['key'] === $key) {
                return $section['score'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, bool>  $checks  libellé => « information présente ? »
     * @return array{key:string,label:string,score:int,total:int,remplis:int,manquants:list<string>}
     */
    private function section(string $key, string $label, array $checks): array
    {
        $total = count($checks);
        $manquants = array_keys(array_filter($checks, fn (bool $ok): bool => ! $ok));
        $remplis = $total - count($manquants);

        return [
            'key' => $key,
            'label' => $label,
            'score' => $total > 0 ? (int) round($remplis / $total * 100) : 100,
            'total' => $total,
            'remplis' => $remplis,
            'manquants' => array_values($manquants),
        ];
    }

    /**
     * L'onglet Étudiant porte désormais l'état civil, la formation, le volume
     * horaire, les dates, la situation sociale et les études.
     *
     * @return array<string, bool>
     */
    private function etudiant(Contract $contract): array
    {
        $c = $contract->candidate;
        $formation = $contract->formation ?? $c?->formationVisee;

        return [
            // État civil
            'Nom' => filled($c?->nom),
            'Prénom' => filled($c?->prenom),
            'Email ou téléphone' => filled($c?->email) || filled($c?->telephone),
            'Date de naissance' => filled($c?->date_naissance),
            'Sexe' => filled($c?->sexe),
            'Nationalité' => filled($c?->nationalite),
            'Adresse' => filled($c?->adresse) && filled($c?->code_postal) && filled($c?->ville),
            'Ville de naissance' => filled($c?->lieu_naissance),
            'N° de sécurité sociale' => filled($c?->num_secu),
            // Situation sociale
            'Régime social' => filled($c?->regime_social),
            'Situation avant contrat' => filled($c?->situation_avant_contrat),
            // Études
            'Diplôme le plus élevé obtenu' => filled($c?->niveau_diplome_max),
            'Dernier diplôme préparé' => filled($c?->dernier_diplome_prepare),
            // Formation
            'Formation' => filled($formation?->libelle),
            'Lieu de formation' => filled($contract->lieuFormationLisible()),
            'Durée de formation' => filled($contract->duree_formation_heures),
            'Date de début' => filled($contract->date_debut),
            'Date de fin' => filled($contract->date_fin),
        ];
    }

    /** @return array<string, bool> */
    private function contrat(Contract $contract): array
    {
        $formation = $contract->formation ?? $contract->candidate?->formationVisee;

        return [
            // Termes du contrat
            'Type de contrat' => filled($contract->type_contrat),
            'Nature du contrat' => filled($contract->nature_contrat),
            'Code RNCP' => filled($contract->code_rncp) || filled($formation?->code_rncp),
            'Intitulé du poste' => filled($contract->emploi_occupe),
            'Durée hebdomadaire du travail' => filled($contract->duree_hebdo_heures),
            'Missions de l\'apprenti' => filled($contract->missions),
            // Calendrier & rémunération (dates du contrat, avec repli formation)
            'Date de début de contrat' => filled($contract->date_debut_contrat) || filled($contract->date_debut),
            'Date de fin de contrat' => filled($contract->date_fin_contrat) || filled($contract->date_fin),
            'Salaire mensuel brut' => filled($contract->salaire_mensuel_brut),
            // Données financières / OPCO
            'Montant total de la formation' => filled($contract->cout_formation),
            'Financement OPCO' => filled($contract->engagement_opco_total) || filled($contract->npec_annuel),
            // Reste à charge entreprise
            'Reste à charge / net à payer' => filled($contract->net_a_payer) || (bool) $contract->reste_a_charge_zero,
        ];
    }

    /** @return array<string, bool> */
    private function entreprise(Contract $contract): array
    {
        $co = $contract->company;
        $tuteur = $contract->tuteur;

        return [
            'Raison sociale' => filled($co?->raison_sociale),
            'SIRET' => filled($co?->siret),
            'Adresse du siège' => filled($co?->adresse) && filled($co?->ville),
            'Adresse de réalisation du contrat' => filled($contract->lieu_execution) || filled($contract->lieu_execution_ville),
            'Caisse de retraite complémentaire' => filled($co?->caisse_retraite),
            'Nombre de salariés' => filled($co?->nombre_salaries),
            'Type d\'employeur' => filled($co?->type_employeur),
            'Entité à facturer' => filled($contract->facturation_entite_nom),
            'Contact entreprise' => (bool) $co?->contactPrincipal()->whereNotNull('email')->exists(),
            'Représentant légal' => (bool) $co?->representantsLegaux()->exists(),
            'Maître d\'apprentissage (nom)' => filled($tuteur?->nom),
            'Maître d\'apprentissage (email)' => filled($tuteur?->email),
        ];
    }

    /** @return array<string, bool> */
    private function gestion(Contract $contract): array
    {
        $co = $contract->company;

        return [
            'OPCO' => $contract->opcoFile?->opco !== null || $co?->opco !== null,
            'Responsable du dossier' => filled($contract->responsable_id),
        ];
    }
}
