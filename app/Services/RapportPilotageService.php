<?php

namespace App\Services;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\InvoiceStatut;
use App\Enums\OpcoStatut;
use App\Enums\PresenceStatut;
use App\Enums\RiskLevel;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\FinancePayment;
use App\Models\Invoice;
use App\Models\OpcoFile;
use App\Models\Presence;
use App\Models\Task;

/**
 * Agrège les indicateurs clés de pilotage pour le rapport Direction (synthèse
 * chiffrée exportable en PDF). Chaque bloc est calculé à la volée.
 */
class RapportPilotageService
{
    /** @return array<string, mixed> */
    public function donnees(): array
    {
        return [
            'genere_le' => now(),
            'candidats' => $this->candidats(),
            'admissions' => $this->admissions(),
            'contrats' => $this->contrats(),
            'opco' => $this->opco(),
            'finance' => $this->finance(),
            'assiduite' => $this->assiduite(),
            'alertes' => Task::where('statut', TaskStatut::EnRetard->value)->count(),
        ];
    }

    private function candidats(): array
    {
        return [
            'actifs' => Candidate::whereNot('statut', CandidateStatut::Rupture->value)->count(),
            'a_placer' => Candidate::where('statut', CandidateStatut::EnRechercheEntreprise->value)->count(),
            'ruptures' => Candidate::where('statut', CandidateStatut::Rupture->value)->count(),
        ];
    }

    private function admissions(): array
    {
        return [
            'validees' => Admission::where('statut', AdmissionStatut::Valide->value)->count(),
            'en_cours' => Admission::whereNotIn('statut', [
                AdmissionStatut::Valide->value,
                AdmissionStatut::Refuse->value,
            ])->count(),
        ];
    }

    private function contrats(): array
    {
        $signes = [
            ContractStatut::Signe->value,
            ContractStatut::TransmisOpco->value,
            ContractStatut::Actif->value,
        ];

        return [
            'signes' => Contract::whereIn('statut_contrat', $signes)->count(),
            'actifs' => Contract::where('statut_contrat', ContractStatut::Actif->value)->count(),
            'rompus' => Contract::where('statut_contrat', ContractStatut::Rompu->value)->count(),
            'a_risque' => Contract::whereIn('risk_level', RiskLevel::aRisque())->count(),
        ];
    }

    private function opco(): array
    {
        return [
            'en_cours' => OpcoFile::whereNotIn('statut', [OpcoStatut::Cloture->value])->count(),
            'bloques' => OpcoFile::whereIn('statut', OpcoStatut::bloques())->count(),
            'montant_accepte' => (float) OpcoFile::sum('montant_accepte'),
        ];
    }

    private function finance(): array
    {
        $facture = (float) Invoice::whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])
            ->sum('montant');
        $encaisse = (float) FinancePayment::sum('montant');

        $enRetard = 0.0;
        Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->with('payments')
            ->get()
            ->each(function (Invoice $invoice) use (&$enRetard): void {
                $enRetard += $invoice->resteAPayer();
            });

        return [
            'facture' => $facture,
            'encaisse' => $encaisse,
            'taux' => $facture > 0 ? (int) round($encaisse / $facture * 100) : 0,
            'impayes' => round($enRetard, 2),
        ];
    }

    private function assiduite(): ?int
    {
        $renseignees = Presence::where('statut', '!=', PresenceStatut::NonRenseigne->value)->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = Presence::whereIn('statut', array_map(
            fn (PresenceStatut $s) => $s->value,
            PresenceStatut::presents(),
        ))->count();

        return (int) round($presents / $renseignees * 100);
    }
}
