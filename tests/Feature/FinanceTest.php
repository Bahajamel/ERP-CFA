<?php

use App\Enums\DocumentType;
use App\Enums\InvoiceStatut;
use App\Finance\InvoiceGenerator;
use App\Models\FinanceLine;
use App\Models\FinancePayment;
use App\Models\Invoice;
use App\StateMachine\InvalidTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// P1-16-1 / P1-16-4 : ligne financière et garde du montant bloqué
// ---------------------------------------------------------------------------

it('refuse une ligne avec un montant bloqué sans motif', function () {
    expect(fn () => FinanceLine::factory()->create([
        'montant_bloque' => 1500,
        'motif_blocage' => null,
    ]))->toThrow(ValidationException::class);
});

it('accepte un montant bloqué accompagné d\'un motif', function () {
    $line = FinanceLine::factory()->bloquee(1500)->create();

    expect($line->estBloque())->toBeTrue()
        ->and($line->motif_blocage)->not->toBeEmpty();
});

it('calcule le facturable sur le montant accepté, sinon l\'attendu', function () {
    $avecAccepte = FinanceLine::factory()->create(['montant_attendu' => 8000, 'montant_accepte' => 7200]);
    $sansAccepte = FinanceLine::factory()->create(['montant_attendu' => 8000, 'montant_accepte' => 0]);

    expect($avecAccepte->montantFacturable())->toBe(7200.0)
        ->and($sansAccepte->montantFacturable())->toBe(8000.0);
});

// ---------------------------------------------------------------------------
// P1-16-3 / P1-16-4 : émission de facture
// ---------------------------------------------------------------------------

it('refuse d\'émettre une facture sans montant', function () {
    $invoice = Invoice::factory()->create(['montant' => 0, 'destinataire' => 'ACME']);

    expect(fn () => $invoice->transitionTo(InvoiceStatut::Emise))
        ->toThrow(InvalidTransitionException::class);
});

it('refuse d\'émettre une facture sans destinataire', function () {
    $invoice = Invoice::factory()->create(['montant' => 3000, 'destinataire' => null]);

    expect(fn () => $invoice->transitionTo(InvoiceStatut::Emise))
        ->toThrow(InvalidTransitionException::class);
});

it('émet une facture valide et horodate l\'émission (n° saisi côté compta)', function () {
    $invoice = Invoice::factory()->create([
        'montant' => 3000,
        'destinataire' => 'ACME SARL',
        'numero' => '2026-042', // numéro fourni par la comptabilité
    ]);

    $invoice->transitionTo(InvoiceStatut::Emise);
    $invoice->refresh();

    expect($invoice->statut)->toBe(InvoiceStatut::Emise)
        ->and($invoice->numero)->toBe('2026-042')
        ->and($invoice->date_emission)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// P1-16-2 : encaissements, retards, auto-clôture
// ---------------------------------------------------------------------------

it('exige qu\'un paiement soit relié à une facture ou une ligne', function () {
    expect(fn () => FinancePayment::create([
        'montant' => 100,
        'date_paiement' => now(),
    ]))->toThrow(ValidationException::class);
});

it('solde une facture par paiements successifs et la passe à Payée', function () {
    $invoice = Invoice::factory()->emise()->create(['montant' => 1000]);

    FinancePayment::create([
        'finance_line_id' => $invoice->finance_line_id,
        'invoice_id' => $invoice->id,
        'montant' => 400,
        'date_paiement' => now(),
    ]);
    $invoice->refresh();
    expect($invoice->statut)->toBe(InvoiceStatut::Emise)
        ->and($invoice->resteAPayer())->toBe(600.0);

    FinancePayment::create([
        'finance_line_id' => $invoice->finance_line_id,
        'invoice_id' => $invoice->id,
        'montant' => 600,
        'date_paiement' => now(),
    ]);
    $invoice->refresh();

    expect($invoice->statut)->toBe(InvoiceStatut::Payee)
        ->and($invoice->resteAPayer())->toBe(0.0);
});

it('rattache le paiement à la ligne de la facture si non précisée', function () {
    $invoice = Invoice::factory()->emise()->create(['montant' => 500]);

    $payment = FinancePayment::create([
        'invoice_id' => $invoice->id,
        'montant' => 200,
        'date_paiement' => now(),
    ]);

    expect($payment->finance_line_id)->toBe($invoice->finance_line_id);
});

it('détecte une facture émise en retard', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 1000,
        'date_echeance' => now()->subDay()->toDateString(),
    ]);

    expect($invoice->estEnRetard())->toBeTrue();
});

it('agrège facturé et encaissé au niveau de la ligne', function () {
    $line = FinanceLine::factory()->create(['montant_attendu' => 5000, 'montant_accepte' => 5000]);
    Invoice::factory()->emise()->for($line, 'financeLine')->create(['montant' => 3000]);
    Invoice::factory()->for($line, 'financeLine')->create(['montant' => 2000]); // brouillon, non compté
    FinancePayment::factory()->for($line, 'financeLine')->create(['montant' => 1200]);

    expect($line->montantFacture())->toBe(3000.0)
        ->and($line->montantEncaisse())->toBe(1200.0)
        ->and($line->resteAEncaisser())->toBe(1800.0);
});

// ---------------------------------------------------------------------------
// P1-16-3 : génération du PDF de facture
// ---------------------------------------------------------------------------

it('génère un PDF de facture archivé dans la GED', function () {
    Storage::fake('public');

    $invoice = Invoice::factory()->emise()->create(['montant' => 4000, 'destinataire' => 'ACME']);

    $document = app(InvoiceGenerator::class)->generer($invoice);

    expect($document->type)->toBe(DocumentType::Facture)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($invoice->documents()->count())->toBe(1);
});
