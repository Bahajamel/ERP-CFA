<?php

namespace App\Services;

use App\Enums\ContractStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\OpcoStatut;
use App\Models\Contract;
use App\Support\RiskAssessment;
use App\Support\RiskFactor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Détection du risque de rupture d'un contrat d'apprentissage.
 *
 * Chaque signal est un « détecteur » indépendant qui renvoie un RiskFactor
 * pondéré (ou null). Le score est la somme plafonnée à 100, traduite en niveau.
 * L'architecture est volontairement extensible : brancher un nouveau signal
 * (ex. absentéisme quand le module assiduité existera) = ajouter un détecteur
 * dans detecteurs() — le reste (score, alertes, dashboard) suit automatiquement.
 */
class RuptureRiskService
{
    /** Durée légale de la période d'essai en apprentissage (jours de présence). */
    private const PERIODE_ESSAI_JOURS = 45;

    /** Évalue le risque de rupture d'un contrat. */
    public function evaluate(Contract $contract): RiskAssessment
    {
        $facteurs = [];

        foreach ($this->detecteurs() as $detecteur) {
            $facteur = $detecteur($contract);

            if ($facteur instanceof RiskFactor) {
                $facteurs[] = $facteur;
            }
        }

        return RiskAssessment::fromFactors($facteurs);
    }

    /**
     * Évalue tous les contrats en cours, persiste l'instantané de risque et
     * retourne le nombre de contrats jugés « à risque » (élevé/critique).
     */
    public function evaluerTous(): int
    {
        $aRisque = 0;

        $this->contratsAEvaluer()->get()->each(function (Contract $contract) use (&$aRisque) {
            $eval = $this->evaluate($contract);

            $contract->forceFill([
                'risk_score' => $eval->score,
                'risk_level' => $eval->level->value,
                'risk_factors' => $eval->factorsToArray(),
                'risk_evaluated_at' => now(),
            ])->saveQuietly();

            if (in_array($eval->level->value, \App\Enums\RiskLevel::aRisque(), true)) {
                $aRisque++;
            }
        });

        return $aRisque;
    }

    /** Requête des contrats en cours, avec les relations nécessaires aux détecteurs. */
    public function contratsAEvaluer(): Builder
    {
        return Contract::query()
            ->whereIn('statut_contrat', ContractStatut::enCours())
            ->with(['candidate', 'company', 'opcoFile']);
    }

    /**
     * Liste ordonnée des détecteurs de signaux. Ajouter un signal ici suffit.
     *
     * @return list<callable(Contract): ?RiskFactor>
     */
    private function detecteurs(): array
    {
        return [
            $this->periodeEssai(...),
            $this->sansMaitreApprentissage(...),
            $this->contratNonSigne(...),
            $this->financementBloque(...),
            $this->financementNonEngage(...),
            // Point d'extension : absentéisme (module assiduité à venir).
        ];
    }

    /** Contrat démarré depuis ≤ 45 j : période d'essai, rupture libre possible. */
    private function periodeEssai(Contract $contract): ?RiskFactor
    {
        $debut = $contract->date_debut;

        if (! $debut instanceof Carbon || $debut->isFuture()) {
            return null;
        }

        if ($debut->lt(now()->subDays(self::PERIODE_ESSAI_JOURS)->startOfDay())) {
            return null;
        }

        return new RiskFactor(
            'periode_essai',
            'En période d\'essai (45 j) — rupture possible sans motif',
            20,
        );
    }

    /** Aucun maître d'apprentissage (tuteur) désigné sur le contrat. */
    private function sansMaitreApprentissage(Contract $contract): ?RiskFactor
    {
        if ($contract->tuteur_id !== null) {
            return null;
        }

        return new RiskFactor(
            'sans_tuteur',
            'Aucun maître d\'apprentissage désigné',
            25,
        );
    }

    /** Apprentissage en cours mais contrat non signé : faille administrative. */
    private function contratNonSigne(Contract $contract): ?RiskFactor
    {
        if ($contract->statut_signature === ContractSignatureStatut::Signe) {
            return null;
        }

        $aDemarre = $contract->statut_contrat === ContractStatut::Actif
            || ($contract->date_debut instanceof Carbon && ! $contract->date_debut->isFuture());

        if (! $aDemarre) {
            return null;
        }

        return new RiskFactor(
            'non_signe',
            'Contrat démarré mais non signé',
            30,
        );
    }

    /** Financement OPCO bloqué (rejeté ou en correction). */
    private function financementBloque(Contract $contract): ?RiskFactor
    {
        $statut = $contract->opcoFile?->statut;

        if ($statut === null || ! in_array($statut->value, OpcoStatut::bloques(), true)) {
            return null;
        }

        return new RiskFactor(
            'opco_bloque',
            'Financement OPCO bloqué (rejeté / en correction)',
            25,
        );
    }

    /** Contrat actif mais dossier OPCO non engagé (retard de sécurisation). */
    private function financementNonEngage(Contract $contract): ?RiskFactor
    {
        if ($contract->statut_contrat !== ContractStatut::Actif) {
            return null;
        }

        $statut = $contract->opcoFile?->statut;
        $nonEngage = $statut === null
            || in_array($statut, [OpcoStatut::NonCree, OpcoStatut::APreparer], true);

        if (! $nonEngage) {
            return null;
        }

        return new RiskFactor(
            'opco_non_engage',
            'Dossier OPCO non engagé alors que le contrat est actif',
            15,
        );
    }
}
