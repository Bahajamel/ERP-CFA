<?php

use App\Enums\DocumentType;
use App\Enums\InvoiceStatut;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Finance\InvoiceGenerator;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');
    Storage::fake(config('documents.disque_prive'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('émet une facture brouillon (Brouillon → Émise) avec numéro et date', function () {
    $invoice = Invoice::factory()->create([
        'statut' => InvoiceStatut::Brouillon,
        'destinataire' => 'OPCO EP',
        'montant' => 4000,
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('emettre', $invoice, data: [
            'numero' => '2026-0001',
            'date_emission' => now()->toDateString(),
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->statut)->toBe(InvoiceStatut::Emise)
        ->and($invoice->numero)->toBe('2026-0001')
        ->and($invoice->date_emission)->not->toBeNull();
});

it('refuse d\'émettre une facture sans destinataire (garde du modèle)', function () {
    $invoice = Invoice::factory()->create([
        'statut' => InvoiceStatut::Brouillon,
        'destinataire' => null,
        'montant' => 4000,
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('emettre', $invoice, data: ['date_emission' => now()->toDateString()]);

    // La garde bloque la transition : la facture reste au brouillon.
    expect($invoice->refresh()->statut)->toBe(InvoiceStatut::Brouillon);
});

it('génère un proforma PDF non vide, sans l\'archiver', function () {
    $invoice = Invoice::factory()->create(['statut' => InvoiceStatut::Brouillon]);

    $contenu = app(InvoiceGenerator::class)->pdf($invoice);

    expect($contenu)->toBeString()
        ->and(strlen($contenu))->toBeGreaterThan(500)
        ->and(str_starts_with($contenu, '%PDF'))->toBeTrue()
        // Le proforma ne crée pas de document en GED.
        ->and($invoice->documents()->count())->toBe(0);
});

it('importe la facture comptable : PDF archivé, n° posé, facture émise', function () {
    $invoice = Invoice::factory()->create([
        'statut' => InvoiceStatut::Brouillon,
        'destinataire' => 'OPCO EP',
        'montant' => 4000,
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('importerFacture', $invoice, data: [
            'numero' => '2026-IMP-9',
            'fichier' => UploadedFile::fake()->create('facture.pdf', 120, 'application/pdf'),
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->numero)->toBe('2026-IMP-9')
        ->and($invoice->importee)->toBeTrue()
        ->and($invoice->statut)->toBe(InvoiceStatut::Emise)
        ->and($invoice->documents()->where('type', DocumentType::Facture->value)->count())->toBe(1);
});

it('annule une facture émise', function () {
    $invoice = Invoice::factory()->emise()->create();

    Livewire::test(ListInvoices::class)
        ->callTableAction('annuler', $invoice)
        ->assertHasNoTableActionErrors();

    expect($invoice->refresh()->statut)->toBe(InvoiceStatut::Annulee);
});

it('affiche la liste des factures aux profils Finance', function () {
    $invoice = Invoice::factory()->emise()->create();

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice]);
});