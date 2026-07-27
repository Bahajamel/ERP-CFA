<?php

namespace App\Services;

use App\Enums\FinanceLineStatut;
use App\Enums\InvoiceStatut;
use App\Models\FinanceLine;
use App\Models\Invoice;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use Illuminate\Support\Collection;

/**
 * Intelligence de la finance : connecte la ligne financière au dossier OPCO,
 * génère les factures dues depuis l'échéancier légal (décret 2025-585) et
 * calcule la cohérence du dossier (tour de contrôle).
 */
class FinanceService
{
    /** Formatage monétaire « 7 200,00 € ». */
    public static function euros(float|string|null $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ').' €';
    }

    /**
     * Crée (ou met à jour) la ligne financière rattachée à un dossier OPCO
     * accepté : reprend le montant accepté par le financeur, sans double saisie.
     * Idempotent (une ligne par dossier OPCO). Retourne null si le dossier n'est
     * pas exploitable (pas de contrat ou montant accepté nul).
     */
    public function synchroniserDepuisOpco(OpcoFile $dossier): ?FinanceLine
    {
        $contract = $dossier->contract;
        $montant = (float) $dossier->montant_accepte;

        if ($contract === null || $montant <= 0) {
            return null;
        }

        $line = FinanceLine::firstOrNew(['opco_file_id' => $dossier->id]);
        $nouvelle = ! $line->exists;

        $line->contract_id = $contract->id;
        $line->libelle = 'Financement OPCO'
            .($contract->formation?->libelle ? ' — '.$contract->formation->libelle : '');
        $line->montant_accepte = $montant;

        if ($nouvelle) {
            $line->montant_attendu = $montant;
        }

        $line->save();

        return $line;
    }

    /**
     * Synchronisation de rattrapage : parcourt TOUS les dossiers OPCO
     * (section Contrats & OPCO) et garantit une ligne financière pour chacun
     * (montant accepté si présent, sinon prévisionnel), puis génère les factures
     * dues. Idempotent (une ligne par dossier OPCO) — sûr à relancer.
     *
     * @return array{lignes:int, factures:int, ignores:int}
     */
    public function synchroniserTousLesDossiers(?int $userId = null): array
    {
        $stats = ['lignes' => 0, 'factures' => 0, 'ignores' => 0];

        OpcoFile::query()->with('contract.formation')->get()->each(function (OpcoFile $dossier) use (&$stats, $userId): void {
            $line = $this->synchroniserDossier($dossier);

            if ($line === null) {
                $stats['ignores']++;

                return;
            }

            $stats['lignes']++;
            $stats['factures'] += $this->genererFacturesDues($line, $userId)->count();
        });

        return $stats;
    }

    /**
     * Ligne financière d'un dossier OPCO pour la synchro globale : reprend le
     * montant accepté par le financeur, ou à défaut le prévisionnel (dossier pas
     * encore accepté), pour que le dossier apparaisse dès maintenant dans Finance.
     * Retourne null si le dossier n'a ni contrat ni montant exploitable.
     */
    private function synchroniserDossier(OpcoFile $dossier): ?FinanceLine
    {
        $contract = $dossier->contract;
        $accepte = (float) $dossier->montant_accepte;
        $reference = $accepte > 0 ? $accepte : (float) $dossier->montant_prevu;

        if ($contract === null || $reference <= 0) {
            return null;
        }

        $line = FinanceLine::firstOrNew(['opco_file_id' => $dossier->id]);
        $nouvelle = ! $line->exists;

        $line->contract_id = $contract->id;
        $line->libelle = 'Financement OPCO'
            .($contract->formation?->libelle ? ' — '.$contract->formation->libelle : '');
        $line->montant_accepte = $accepte;

        if ($nouvelle) {
            $line->montant_attendu = $reference;
        }

        $line->save();

        return $line;
    }

    /**
     * Génère les factures (proforma, brouillon) pour les échéances OPCO
     * arrivées à terme et pas encore facturées. Anti-doublon : une facture non
     * annulée par échéance. Retourne les factures créées.
     *
     * @return Collection<int, Invoice>
     */
    public function genererFacturesDues(FinanceLine $line, ?int $userId = null): Collection
    {
        $dossier = $line->opcoFile;

        if ($dossier === null) {
            return collect();
        }

        $destinataire = $dossier->opcoEffectif()?->nom ?? 'OPCO';
        $creees = collect();

        $dossier->payments()
            ->whereDate('date_prevue', '<=', now())
            ->orderBy('ordre')
            ->get()
            ->each(function (OpcoPayment $payment) use ($line, $destinataire, $userId, $creees): void {
                if ($this->echeanceDejaFacturee($payment)) {
                    return;
                }

                $creees->push($line->invoices()->create([
                    'opco_payment_id' => $payment->id,
                    'destinataire' => $destinataire,
                    'montant' => $payment->montant_prevu,
                    'date_echeance' => $payment->date_prevue,
                    'statut' => InvoiceStatut::Brouillon->value,
                    'created_by' => $userId,
                ]));
            });

        return $creees;
    }

    /** Une facture non annulée couvre-t-elle déjà cette échéance OPCO ? */
    public function echeanceDejaFacturee(OpcoPayment $payment): bool
    {
        return Invoice::query()
            ->where('opco_payment_id', $payment->id)
            ->where('statut', '!=', InvoiceStatut::Annulee->value)
            ->exists();
    }

    /**
     * Tour de contrôle : incohérences détectées + score de santé (0-100).
     *
     * @return array{statut:FinanceLineStatut, score:int, issues:list<string>, facturable:float, facture:float, encaisse:float}
     */
    public function coherence(FinanceLine $line): array
    {
        $line->loadMissing(['invoices', 'opcoFile.payments']);

        $dossier = $line->opcoFile;
        $facturable = $line->montantFacturable();
        $facture = $line->montantFacture();
        $encaisse = $line->montantEncaisse();

        $issues = [];

        if ($dossier !== null && (float) $dossier->montant_accepte > 0
            && round((float) $line->montant_accepte, 2) !== round((float) $dossier->montant_accepte, 2)) {
            $issues[] = 'Le montant accepté de la ligne ('.self::euros($line->montant_accepte)
                .') diffère du montant accepté par l\'OPCO ('.self::euros($dossier->montant_accepte).').';
        }

        if ($facturable > 0 && $facture > round($facturable, 2)) {
            $issues[] = 'Le montant facturé ('.self::euros($facture).') dépasse le finançable ('
                .self::euros($facturable).').';
        }

        if ($dossier !== null) {
            $dues = $dossier->payments
                ->filter(fn (OpcoPayment $p) => $p->date_prevue !== null && ! $p->date_prevue->isFuture());
            $nonFacturees = $dues->reject(fn (OpcoPayment $p) => $this->echeanceDejaFacturee($p))->count();

            if ($nonFacturees > 0) {
                $issues[] = $nonFacturees.' échéance(s) OPCO arrivée(s) à terme ne sont pas encore facturées.';
            }
        }

        $retard = $line->invoices->filter(fn (Invoice $i) => $i->estEnRetard())->count();

        if ($retard > 0) {
            $issues[] = $retard.' facture(s) échue(s) impayée(s) — relance recommandée.';
        }

        if ($line->estBloque()) {
            $issues[] = 'Ligne partiellement bloquée ('.self::euros($line->montant_bloque).') : '
                .($line->motif_blocage ?: 'motif à préciser').'.';
        }

        return [
            'statut' => $line->statut(),
            'score' => max(0, 100 - 25 * count($issues)),
            'issues' => $issues,
            'facturable' => $facturable,
            'facture' => $facture,
            'encaisse' => $encaisse,
        ];
    }
}
