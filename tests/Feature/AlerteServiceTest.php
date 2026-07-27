<?php

use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\TaskStatut;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\Task;
use App\Models\User;
use App\Services\AlerteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée une alerte de signature de contrat, de façon idempotente', function () {
    $contract = Contract::factory()->create(['statut_contrat' => ContractStatut::ManqueSignature]);
    $cle = "contrat:signature:{$contract->id}";

    (new AlerteService)->genererAlertes();
    expect(Task::where('cle', $cle)->count())->toBe(1);

    // Deuxième passage : pas de doublon.
    (new AlerteService)->genererAlertes();
    expect(Task::where('cle', $cle)->count())->toBe(1);
});

it('alerte sur un contrat envoyé pour signature', function () {
    $contract = Contract::factory()->create(['statut_contrat' => ContractStatut::ManqueSignature]);

    (new AlerteService)->genererAlertes();

    expect(Task::where('cle', "contrat:signature:{$contract->id}")->exists())->toBeTrue();
});

it('alerte sur un dossier OPCO sans retour depuis plus de 30 jours', function () {
    $file = OpcoFile::factory()->create([
        'statut' => OpcoStatut::Depose,
        'date_depot' => now()->subDays(40),
    ]);

    (new AlerteService)->genererAlertes();

    expect(Task::where('cle', "opco:sans_retour:{$file->id}")->exists())->toBeTrue();
});

it('alerte sur une échéance de versement à venir', function () {
    $file = OpcoFile::factory()->create();
    $payment = OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'statut' => PaymentStatut::Attendu,
        'date_prevue' => now()->addDays(3),
    ]);

    (new AlerteService)->genererAlertes();

    expect(Task::where('cle', "opco:echeance:{$payment->id}")->exists())->toBeTrue();
});

it('passe les tâches dont l\'échéance est dépassée en retard', function () {
    $task = Task::factory()->create([
        'due_date' => now()->subDays(2),
        'statut' => TaskStatut::AFaire,
    ]);

    (new AlerteService)->genererAlertes();

    expect($task->fresh()->statut)->toBe(TaskStatut::EnRetard);
});

it('notifie in-app la personne concernée par l\'alerte', function () {
    $responsable = User::factory()->create();
    OpcoFile::factory()->create([
        'statut' => OpcoStatut::Depose,
        'date_depot' => now()->subDays(40),
        'responsable_correction_id' => $responsable->id,
    ]);

    (new AlerteService)->genererAlertes();

    expect($responsable->notifications()->count())->toBeGreaterThan(0);
});

it('exécute la commande de génération des alertes', function () {
    Contract::factory()->create(['statut_contrat' => ContractStatut::ManqueSignature]);

    $this->artisan('app:generer-alertes')->assertSuccessful();
});
