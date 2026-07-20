<?php

namespace App\Finance;

use App\Enums\InvoiceStatut;
use App\Models\FinanceLine;
use App\Models\FinancePayment;
use App\Models\Invoice;

/**
 * Fournisseur de données du dashboard Finance.
 *
 * Deux blocs NETTEMENT séparés :
 *  - RÉEL : agrégats calculés depuis la base (KPI montants, graphique, factures
 *    et paiements récents). Repli sur des lignes de démonstration uniquement si
 *    la base ne contient encore aucune facture/paiement.
 *  - DÉMO : parties non encore modélisées (cash bloqué « par raison » catégorisé,
 *    variations « vs période précédente » faute d'historique, actions prioritaires).
 *    Ces méthodes sont isolées et commentées « DÉMO » pour être branchées plus tard.
 *
 * Aucun modèle/migration modifié : lecture seule.
 */
class FinanceDashboardData
{
    /* ===================================================================== */
    /*  RÉEL — agrégats base de données */
    /* ===================================================================== */

    /** Les 6 cartes KPI (valeurs réelles + variations de démonstration). */
    public function kpis(): array
    {
        $attendu = (float) FinanceLine::sum('montant_attendu');
        $facture = (float) Invoice::whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])->sum('montant');
        $encaisse = (float) FinancePayment::sum('montant');
        $bloque = (float) FinanceLine::where('montant_bloque', '>', 0)->sum('montant_bloque');
        $enRetard = $this->impayesEchus()['montant'];
        $opcoBloques = FinanceLine::where('montant_bloque', '>', 0)->whereNotNull('opco_file_id')->count();

        // DÉMO : variations « vs période précédente » (pas d'historique en base).
        $v = $this->variationsDemo();

        // `zone` = section ouverte au clic sur la carte (opco | contrats).
        return [
            ['key' => 'attendu', 'label' => 'Montant attendu', 'valeur' => $attendu, 'unite' => '€', 'description' => 'Prévisionnel total', 'variation' => $v['attendu'], 'couleur' => 'blue', 'icon' => 'heroicon-o-calendar-days', 'zone' => 'opco'],
            ['key' => 'facture', 'label' => 'Montant facturé', 'valeur' => $facture, 'unite' => '€', 'description' => 'Total facturé', 'variation' => $v['facture'], 'couleur' => 'teal', 'icon' => 'heroicon-o-document-currency-euro', 'zone' => 'opco'],
            ['key' => 'encaisse', 'label' => 'Montant encaissé', 'valeur' => $encaisse, 'unite' => '€', 'description' => 'Paiements reçus', 'variation' => $v['encaisse'], 'couleur' => 'green', 'icon' => 'heroicon-o-banknotes', 'zone' => 'opco'],
            ['key' => 'retard', 'label' => 'Montant en retard', 'valeur' => $enRetard, 'unite' => '€', 'description' => 'Factures échues', 'variation' => $v['retard'], 'couleur' => 'orange', 'icon' => 'heroicon-o-clock', 'zone' => 'opco'],
            ['key' => 'bloque', 'label' => 'Montant bloqué', 'valeur' => $bloque, 'unite' => '€', 'description' => 'En attente d\'action', 'variation' => $v['bloque'], 'couleur' => 'red', 'icon' => 'heroicon-o-lock-closed', 'zone' => 'contrats'],
            ['key' => 'opco', 'label' => 'Dossiers OPCO bloqués', 'valeur' => $opcoBloques, 'unite' => '', 'description' => 'Dossiers', 'variation' => $v['opco'], 'couleur' => 'purple', 'icon' => 'heroicon-o-document-text', 'zone' => 'opco'],
        ];
    }

    /** Données du graphique en barres « Suivi financier ». */
    public function graphique(): array
    {
        $attendu = (float) FinanceLine::sum('montant_attendu');
        $facture = (float) Invoice::whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])->sum('montant');
        $encaisse = (float) FinancePayment::sum('montant');
        $bloque = (float) FinanceLine::where('montant_bloque', '>', 0)->sum('montant_bloque');
        $enRetard = $this->impayesEchus()['montant'];

        $bars = [
            ['label' => 'Montant attendu', 'valeur' => $attendu, 'couleur' => 'blue'],
            ['label' => 'Montant facturé', 'valeur' => $facture, 'couleur' => 'teal'],
            ['label' => 'Montant encaissé', 'valeur' => $encaisse, 'couleur' => 'green'],
            ['label' => 'Montant bloqué', 'valeur' => $bloque, 'couleur' => 'red'],
            ['label' => 'Montant en retard', 'valeur' => $enRetard, 'couleur' => 'orange'],
        ];

        // Repli démo si la base est vide (rien à visualiser) pour un rendu propre.
        if (array_sum(array_column($bars, 'valeur')) <= 0) {
            $bars = [
                ['label' => 'Montant attendu', 'valeur' => 125000, 'couleur' => 'blue'],
                ['label' => 'Montant facturé', 'valeur' => 82500, 'couleur' => 'teal'],
                ['label' => 'Montant encaissé', 'valeur' => 64000, 'couleur' => 'green'],
                ['label' => 'Montant bloqué', 'valeur' => 23600, 'couleur' => 'red'],
                ['label' => 'Montant en retard', 'valeur' => 14800, 'couleur' => 'orange'],
            ];
        }

        return ['max' => max(1, max(array_column($bars, 'valeur'))), 'bars' => $bars];
    }

    /** 5 dernières factures (réelles, sinon démo). */
    public function facturesRecentes(): array
    {
        $invoices = Invoice::query()
            ->with(['financeLine.contract.candidate', 'financeLine.contract.company.opco', 'financeLine.opcoFile.opco', 'payments'])
            ->latest('date_emission')->latest('id')->limit(5)->get();

        if ($invoices->isEmpty()) {
            return $this->facturesDemo();
        }

        return $invoices->map(function (Invoice $i): array {
            $ligne = $i->financeLine;

            return [
                'facture' => $i->numero ?: 'FAC-'.str_pad((string) $i->id, 3, '0', STR_PAD_LEFT),
                'contrat' => $ligne?->contract_id ? 'CTR-'.str_pad((string) $ligne->contract_id, 3, '0', STR_PAD_LEFT) : '—',
                'entreprise' => $ligne?->contract?->company?->raison_sociale ?? '—',
                'opco' => $ligne?->opcoFile?->opco?->nom ?? $ligne?->contract?->company?->opco?->nom ?? '—',
                'montant' => (float) $i->montant,
                'echeance' => $i->date_echeance?->format('d/m/Y') ?? '—',
                'statut' => $this->statutFacture($i),
            ];
        })->all();
    }

    /** 5 derniers paiements (réels, sinon démo). */
    public function paiementsRecents(): array
    {
        $paiements = FinancePayment::query()
            ->with(['invoice.financeLine.opcoFile.opco', 'invoice'])
            ->latest('date_paiement')->latest('id')->limit(5)->get();

        if ($paiements->isEmpty()) {
            return $this->paiementsDemo();
        }

        return $paiements->map(function (FinancePayment $p): array {
            $invoice = $p->invoice;
            $solde = $invoice && $invoice->resteAPayer() <= 0;

            return [
                'date' => $p->date_paiement?->format('d/m/Y') ?? '—',
                'facture' => $invoice?->numero ?: ($invoice ? 'FAC-'.str_pad((string) $invoice->id, 3, '0', STR_PAD_LEFT) : '—'),
                'payeur' => $invoice?->financeLine?->opcoFile?->opco?->nom ?? $p->moyen ?? '—',
                'montant' => (float) $p->montant,
                'statut' => $solde ? ['label' => 'Reçu', 'color' => 'green'] : ['label' => 'Partiel', 'color' => 'orange'],
            ];
        })->all();
    }

    /** Impayés échus : montant restant dû + nombre (aligné sur FinanceRecouvrementStats). */
    private function impayesEchus(): array
    {
        $montant = 0.0;
        $count = 0;

        Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->with('payments')
            ->get()
            ->each(function (Invoice $invoice) use (&$montant, &$count): void {
                $reste = $invoice->resteAPayer();
                if ($reste > 0) {
                    $montant += $reste;
                    $count++;
                }
            });

        return ['montant' => $montant, 'count' => $count];
    }

    /** Statut d'affichage d'une facture (retard prioritaire sur le statut brut). */
    private function statutFacture(Invoice $invoice): array
    {
        if ($invoice->estEnRetard()) {
            return ['label' => 'En retard', 'color' => 'red'];
        }

        return match ($invoice->statut) {
            InvoiceStatut::Brouillon => ['label' => 'À émettre', 'color' => 'slate'],
            InvoiceStatut::Emise => ['label' => 'Émise', 'color' => 'blue'],
            InvoiceStatut::Payee => ['label' => 'Payée', 'color' => 'green'],
            InvoiceStatut::Annulee => ['label' => 'Annulée', 'color' => 'gray'],
        };
    }

    /* ===================================================================== */
    /*  DÉMO — à brancher plus tard (données d'illustration) */
    /* ===================================================================== */

    /**
     * DÉMO — Cash bloqué par raison. Le modèle ne stocke qu'un `motif_blocage`
     * texte libre (pas de catégorie ni de responsable) : ces lignes sont
     * illustratives. Évolution : catégoriser `motif_blocage` + rattacher un
     * service responsable pour agréger réellement.
     */
    public function cashBloqueParRaison(): array
    {
        // `zone` = section vers laquelle pointe l'action (contrats | opco).
        return [
            ['raison' => 'Contrat non signé', 'montant' => 12000, 'dossiers' => 4, 'responsable' => 'Administratif', 'action' => 'voir', 'couleur' => 'red', 'zone' => 'contrats'],
            ['raison' => 'Dossier OPCO rejeté', 'montant' => 8500, 'dossiers' => 2, 'responsable' => 'OPCO', 'action' => 'voir', 'couleur' => 'orange', 'zone' => 'opco'],
            ['raison' => 'Pièce manquante', 'montant' => 4200, 'dossiers' => 3, 'responsable' => 'Admission', 'action' => 'voir', 'couleur' => 'blue', 'zone' => 'contrats'],
            ['raison' => 'Service fait non validé', 'montant' => 6000, 'dossiers' => 3, 'responsable' => 'Scolarité', 'action' => 'voir', 'couleur' => 'purple', 'zone' => 'opco'],
            ['raison' => 'Facture non émise', 'montant' => 5500, 'dossiers' => 2, 'responsable' => 'Finance', 'action' => 'voir', 'couleur' => 'teal', 'zone' => 'opco'],
            ['raison' => 'Paiement en retard', 'montant' => 7800, 'dossiers' => 4, 'responsable' => 'Finance', 'action' => 'relancer', 'couleur' => 'orange', 'zone' => 'opco'],
        ];
    }

    /**
     * DÉMO — Actions prioritaires. Dérivable plus tard (factures en retard,
     * lignes bloquées, échéances OPCO non facturées via FinanceService::coherence()).
     */
    public function actionsPrioritaires(): array
    {
        return [
            ['titre' => 'Corriger le dossier OPCO de Karim Benali', 'soustexte' => 'Motif rejet manquant', 'date' => '10/07', 'icon' => 'heroicon-o-document-text'],
            ['titre' => 'Relancer AKTO pour la facture FAC-2026-003', 'soustexte' => 'Paiement en retard', 'date' => '12/07', 'icon' => 'heroicon-o-clock'],
            ['titre' => 'Valider le service fait de juillet pour la cohorte EPR', 'soustexte' => 'Service fait non validé', 'date' => '12/07', 'icon' => 'heroicon-o-check-circle'],
            ['titre' => 'Ajouter la pièce manquante du contrat CTR-006', 'soustexte' => 'Pièce manquante', 'date' => '14/07', 'icon' => 'heroicon-o-paper-clip'],
            ['titre' => 'Émettre la facture du contrat CTR-009', 'soustexte' => 'Facture non émise', 'date' => '15/07', 'icon' => 'heroicon-o-document-plus'],
        ];
    }

    /** DÉMO — variations « vs période précédente » (pas d'historique en base). */
    private function variationsDemo(): array
    {
        return [
            'attendu' => '+12 %', 'facture' => '+8 %', 'encaisse' => '+15 %',
            'retard' => '+20 %', 'bloque' => '+5 %', 'opco' => '+2',
        ];
    }

    /** DÉMO — factures récentes (repli si base vide). */
    private function facturesDemo(): array
    {
        return [
            ['facture' => 'FAC-2026-001', 'contrat' => 'CTR-001', 'entreprise' => 'Restaurant Alpha', 'opco' => 'AKTO', 'montant' => 3500, 'echeance' => '15/07/2026', 'statut' => ['label' => 'Émise', 'color' => 'blue']],
            ['facture' => 'FAC-2026-002', 'contrat' => 'CTR-002', 'entreprise' => 'Boulangerie Beta', 'opco' => 'OPCO EP', 'montant' => 4200, 'echeance' => '20/07/2026', 'statut' => ['label' => 'Payée', 'color' => 'green']],
            ['facture' => 'FAC-2026-003', 'contrat' => 'CTR-003', 'entreprise' => 'Hôtel Gamma', 'opco' => 'AFDAS', 'montant' => 2800, 'echeance' => '01/07/2026', 'statut' => ['label' => 'En retard', 'color' => 'red']],
            ['facture' => 'FAC-2026-004', 'contrat' => 'CTR-004', 'entreprise' => 'Société Delta', 'opco' => 'AKTO', 'montant' => 5000, 'echeance' => '25/07/2026', 'statut' => ['label' => 'Bloquée', 'color' => 'orange']],
            ['facture' => 'FAC-2026-005', 'contrat' => 'CTR-005', 'entreprise' => 'Entreprise Zeta', 'opco' => 'OPCO EP', 'montant' => 3200, 'echeance' => '05/08/2026', 'statut' => ['label' => 'À émettre', 'color' => 'slate']],
        ];
    }

    /** DÉMO — paiements récents (repli si base vide). */
    private function paiementsDemo(): array
    {
        return [
            ['date' => '05/07/2026', 'facture' => 'FAC-2026-002', 'payeur' => 'OPCO EP', 'montant' => 4200, 'statut' => ['label' => 'Reçu', 'color' => 'green']],
            ['date' => '06/07/2026', 'facture' => 'FAC-2026-005', 'payeur' => 'AKTO', 'montant' => 2000, 'statut' => ['label' => 'Partiel', 'color' => 'orange']],
            ['date' => '08/07/2026', 'facture' => 'FAC-2026-001', 'payeur' => 'AKTO', 'montant' => 3500, 'statut' => ['label' => 'Reçu', 'color' => 'green']],
            ['date' => '09/07/2026', 'facture' => 'FAC-2026-003', 'payeur' => 'AFDAS', 'montant' => null, 'statut' => ['label' => 'En attente', 'color' => 'blue']],
            ['date' => '10/07/2026', 'facture' => 'FAC-2026-004', 'payeur' => 'AKTO', 'montant' => null, 'statut' => ['label' => 'En attente', 'color' => 'blue']],
        ];
    }
}
