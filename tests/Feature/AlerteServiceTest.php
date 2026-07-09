<?php

use App\Enums\AdmissionStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\Task;
use App\Services\AlerteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admissionIncomplete(): Admission
{
    $admission = Admission::factory()->create(['statut' => AdmissionStatut::AVerifier]);
    $admission->genererChecklistObligatoire();

    return $admission;
}

it('crée une alerte pour un dossier d\'admission incomplet, de façon idempotente', function () {
    $admission = admissionIncomplete();
    $cle = "admission:incomplete:{$admission->id}";

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
    $admission = admissionIncomplete();
    $commercial = $admission->candidate->commercial;

    (new AlerteService)->genererAlertes();

    expect($commercial->notifications()->count())->toBeGreaterThan(0);
});

it('exécute la commande de génération des alertes', function () {
    admissionIncomplete();

    $this->artisan('app:generer-alertes')->assertSuccessful();
});
