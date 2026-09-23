<?php

use App\Enums\DocumentType;
use App\Enums\InvoiceStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Finance\InvoiceGenerator;
use App\Models\Invoice;
use App\Models\Task;
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

it('encaisse partiellement une facture émise : elle reste émise, le reste baisse', function () {
    $invoice = Invoice::factory()->emise()->create(['montant' => 4000]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('encaisser', $invoice, data: [
            'montant' => 1500,
            'date_paiement' => now()->toDateString(),
            'moyen' => 'Virement',
            'reference' => 'VIR-2026-01',
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->statut)->toBe(InvoiceStatut::Emise)
        ->and($invoice->montantPaye())->toBe(1500.0)
        ->and($invoice->resteAPayer())->toBe(2500.0)
        ->and($invoice->payments()->count())->toBe(1);
});

it('encaisse le solde : la facture bascule automatiquement en Payée', function () {
    $invoice = Invoice::factory()->emise()->create(['montant' => 4000]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('encaisser', $invoice, data: [
            'montant' => 4000,
            'date_paiement' => now()->toDateString(),
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->statut)->toBe(InvoiceStatut::Payee)
        ->and($invoice->resteAPayer())->toBe(0.0);
});

it('refuse un encaissement supérieur au reste à payer (garde du formulaire)', function () {
    $invoice = Invoice::factory()->emise()->create(['montant' => 4000]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('encaisser', $invoice, data: [
            'montant' => 5000,
            'date_paiement' => now()->toDateString(),
        ])
        ->assertHasTableActionErrors(['montant']);

    expect($invoice->refresh()->payments()->count())->toBe(0);
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

it('ouvre une tâche de relance pour une facture impayée échue (commande)', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 4000,
        'date_echeance' => now()->subDays(20)->toDateString(),
    ]);

    $this->artisan('finance:relancer-impayes')->assertSuccessful();

    $tache = Task::where('cle', $invoice->cleRelance())->first();
    expect($tache)->not->toBeNull()
        ->and($tache->statut)->toBe(TaskStatut::AFaire)
        ->and($tache->source)->toBe('auto')
        // 20 jours de retard → priorité Haute (≥ 15 jours).
        ->and($tache->priorite)->toBe(TaskPriorite::Haute);
});

it('ne crée pas de relance pour une facture soldée ou non échue', function () {
    Invoice::factory()->emise()->create(['date_echeance' => now()->addDays(10)->toDateString()]);
    Invoice::factory()->create(['statut' => InvoiceStatut::Brouillon, 'date_echeance' => now()->subDays(5)->toDateString()]);

    $this->artisan('finance:relancer-impayes')->assertSuccessful();

    expect(Task::where('source', 'auto')->count())->toBe(0);
});

it('est idempotente : deux passages ne créent qu\'une seule tâche de relance', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 4000,
        'date_echeance' => now()->subDays(40)->toDateString(),
    ]);

    $this->artisan('finance:relancer-impayes')->assertSuccessful();
    $this->artisan('finance:relancer-impayes')->assertSuccessful();

    $taches = Task::where('cle', $invoice->cleRelance())->get();
    expect($taches)->toHaveCount(1)
        // 40 jours de retard → priorité Urgente (≥ 30 jours).
        ->and($taches->first()->priorite)->toBe(TaskPriorite::Urgente);
});

it('clôt la relance quand la facture est soldée', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 4000,
        'date_echeance' => now()->subDays(10)->toDateString(),
    ]);

    $invoice->ouvrirRelance();
    expect(Task::where('cle', $invoice->cleRelance())->first()->statut)->toBe(TaskStatut::AFaire);

    // Encaissement du solde → la facture passe « Payée » → la relance se clôt.
    Livewire::test(ListInvoices::class)
        ->callTableAction('encaisser', $invoice, data: [
            'montant' => 4000,
            'date_paiement' => now()->toDateString(),
        ])
        ->assertHasNoTableActionErrors();

    expect($invoice->refresh()->statut)->toBe(InvoiceStatut::Payee)
        ->and(Task::where('cle', $invoice->cleRelance())->first()->statut)->toBe(TaskStatut::Terminee);
});

it('permet une relance manuelle depuis l\'écran Factures', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 4000,
        'date_echeance' => now()->subDays(8)->toDateString(),
    ]);

    Livewire::test(ListInvoices::class)
        ->callTableAction('relancer', $invoice)
        ->assertHasNoTableActionErrors();

    $tache = Task::where('cle', $invoice->cleRelance())->first();
    expect($tache)->not->toBeNull()
        // 8 jours de retard → priorité Normale (< 15 jours).
        ->and($tache->priorite)->toBe(TaskPriorite::Normale);
});