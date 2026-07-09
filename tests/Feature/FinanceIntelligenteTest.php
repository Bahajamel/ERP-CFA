<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\FinanceLineStatut;
use App\Enums\InvoiceStatut;
use App\Enums\OpcoStatut;
use App\Models\Contract;
use App\Models\FinanceLine;
use App\Models\FinancePayment;
use App\Models\Invoice;
use App\Models\OpcoFile;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Dossier OPCO accepté (échéancier généré) avec un montant donné. */
function dossierAccepte(float $montant = 8000): OpcoFile
{
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
        'date_debut' => now()->subMonths(2)->toDateString(),
        'date_fin' => now()->addMonths(12)->toDateString(),
    ]);

    $opco = OpcoFile::factory()->create([
        'contract_id' => $contract->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => null,
    ]);
    $opco->forceFill(['montant_accepte' => $montant])->save();
    $opco->transitionTo(OpcoStatut::Accepte);

    return $opco->fresh();
}

it('crée automatiquement la ligne financière à l\'acceptation du dossier OPCO', function () {
    $opco = dossierAccepte(8000);

    $line = FinanceLine::where('opco_file_id', $opco->id)->first();

    expect($line)->not->toBeNull()
        ->and((float) $line->montant_accepte)->toBe(8000.0)
        ->and((float) $line->montant_attendu)->toBe(8000.0)
        ->and($line->contract_id)->toBe($opco->contract_id);
});

it('ne crée qu\'une seule ligne financière par dossier OPCO (idempotent)', function () {
    $opco = dossierAccepte(8000);

    // Rejouer la synchronisation ne duplique pas la ligne.
    app(FinanceService::class)->synchroniserDepuisOpco($opco);
    app(FinanceService::class)->synchroniserDepuisOpco($opco);

    expect(FinanceLine::where('opco_file_id', $opco->id)->count())->toBe(1);
});

it('calcule un statut « À facturer » sur une ligne neuve', function () {
    $line = FinanceLine::where('opco_file_id', dossierAccepte()->id)->first();

    expect($line->statut())->toBe(FinanceLineStatut::ABacturer);
});

it('calcule « Bloquée » quand un montant est bloqué', function () {
    $line = FinanceLine::where('opco_file_id', dossierAccepte()->id)->first();
    $line->forceFill(['montant_bloque' => 500, 'motif_blocage' => 'Litige'])->save();

    expect($line->statut())->toBe(FinanceLineStatut::Bloquee);
});

it('calcule « En retard » quand une facture émise est échue et impayée', function () {
    $line = FinanceLine::where('opco_file_id', dossierAccepte()->id)->first();
    $line->invoices()->create([
        'statut' => InvoiceStatut::Emise->value,
        'destinataire' => 'OPCO 2i',
        'numero' => 'F-1',
        'montant' => 2000,
        'date_emission' => now()->subDays(60)->toDateString(),
        'date_echeance' => now()->subDays(30)->toDateString(),
    ]);

    expect($line->fresh()->statut())->toBe(FinanceLineStatut::EnRetard);
});

it('calcule « Soldée » quand tout le finançable est encaissé', function () {
    $line = FinanceLine::where('opco_file_id', dossierAccepte(8000)->id)->first();
    $invoice = $line->invoices()->create([
        'statut' => InvoiceStatut::Emise->value,
        'destinataire' => 'OPCO 2i',
        'numero' => 'F-2',
        'montant' => 8000,
        'date_emission' => now()->toDateString(),
        'date_echeance' => now()->addDays(30)->toDateString(),
    ]);
    FinancePayment::create([
        'invoice_id' => $invoice->id,
        'finance_line_id' => $line->id,
        'montant' => 8000,
        'date_paiement' => now()->toDateString(),
    ]);

    expect($line->fresh()->statut())->toBe(FinanceLineStatut::Soldee);
});

it('génère les factures des échéances OPCO arrivées à terme, sans doublon', function () {
    $opco = dossierAccepte(8000);
    $line = FinanceLine::where('opco_file_id', $opco->id)->first();

    // Antidater la première échéance pour la rendre « due ».
    $echeance = $opco->payments()->orderBy('ordre')->first();
    $echeance->forceFill(['date_prevue' => now()->subDay()->toDateString()])->save();

    $creees = app(FinanceService::class)->genererFacturesDues($line, null);
    expect($creees)->toHaveCount(1)
        ->and($creees->first()->opco_payment_id)->toBe($echeance->id)
        ->and($creees->first()->statut)->toBe(InvoiceStatut::Brouillon);

    // Rejouer : aucune nouvelle facture (anti-doublon par échéance).
    $rejoue = app(FinanceService::class)->genererFacturesDues($line, null);
    expect($rejoue)->toHaveCount(0)
        ->and(Invoice::where('opco_payment_id', $echeance->id)->count())->toBe(1);
});

it('détecte une incohérence de montant entre la ligne et l\'OPCO (tour de contrôle)', function () {
    $opco = dossierAccepte(8000);
    $line = FinanceLine::where('opco_file_id', $opco->id)->first();

    // Fausser le montant accepté de la ligne.
    $line->forceFill(['montant_accepte' => 5000])->save();

    $etat = app(FinanceService::class)->coherence($line->fresh());

    expect($etat['score'])->toBeLessThan(100)
        ->and(collect($etat['issues'])->contains(fn ($i) => str_contains($i, 'diffère du montant accepté par l\'OPCO')))->toBeTrue();
});
