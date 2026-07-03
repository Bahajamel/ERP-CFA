<?php

use App\Enums\InvoiceStatut;
use App\Enums\TaskStatut;
use App\Filament\Widgets\FinanceRecouvrementStats;
use App\Models\FinancePayment;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\AlerteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('crée une relance pour une facture émise, échue et impayée', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 1000,
        'date_echeance' => now()->subDays(10)->toDateString(),
    ]);

    app(AlerteService::class)->genererAlertes();

    expect(Task::where('cle', $invoice->cleRelance())->exists())->toBeTrue();
});

it('ne relance pas une facture dont l\'échéance n\'est pas dépassée', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 1000,
        'date_echeance' => now()->addDays(10)->toDateString(),
    ]);

    app(AlerteService::class)->genererAlertes();

    expect(Task::where('cle', $invoice->cleRelance())->exists())->toBeFalse();
});

it('ne duplique pas la relance à chaque passage (idempotence)', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 1000,
        'date_echeance' => now()->subDays(10)->toDateString(),
    ]);

    app(AlerteService::class)->genererAlertes();
    app(AlerteService::class)->genererAlertes();

    expect(Task::where('cle', $invoice->cleRelance())->count())->toBe(1);
});

it('clôt la relance quand la facture est soldée', function () {
    $invoice = Invoice::factory()->emise()->create([
        'montant' => 1000,
        'date_echeance' => now()->subDays(5)->toDateString(),
    ]);
    app(AlerteService::class)->genererAlertes();

    FinancePayment::create([
        'finance_line_id' => $invoice->finance_line_id,
        'invoice_id' => $invoice->id,
        'montant' => 1000,
        'date_paiement' => now()->toDateString(),
    ]);
    $invoice->refresh();

    expect($invoice->statut)->toBe(InvoiceStatut::Payee)
        ->and(Task::where('cle', $invoice->cleRelance())->value('statut'))->toBe(TaskStatut::Terminee);
});

it('affiche le widget de recouvrement aux profils Finance', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Finance']);
    $this->actingAs($user);

    Invoice::factory()->emise()->create(['montant' => 2000]);

    expect(FinanceRecouvrementStats::canView())->toBeTrue();

    Livewire::test(FinanceRecouvrementStats::class)
        ->assertSuccessful()
        ->assertSee('Taux de recouvrement');
});
