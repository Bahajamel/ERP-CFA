<?php

use App\Models\Admission;
use App\Models\AdmissionChecklistItem;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Formation;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Note;
use App\Models\Opco;
use App\Models\OpcoFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crée un enregistrement valide pour chaque factory du socle', function (string $model) {
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue()
        ->and($model::query()->whereKey($instance->getKey())->exists())->toBeTrue();
})->with([
    'Formation' => [Formation::class],
    'Opco' => [Opco::class],
    'User' => [User::class],
    'Candidate' => [Candidate::class],
    'Company' => [Company::class],
    'CompanyContact' => [CompanyContact::class],
    'Need' => [Need::class],
    'Matching' => [Matching::class],
    'Document' => [Document::class],
    'Admission' => [Admission::class],
    'AdmissionChecklistItem' => [AdmissionChecklistItem::class],
    'Contract' => [Contract::class],
    'OpcoFile' => [OpcoFile::class],
    'Task' => [Task::class],
    'Note' => [Note::class],
]);

it('résout les relations polymorphes (notes & documents) sur un candidat', function () {
    $candidate = Candidate::factory()->create();
    $note = Note::factory()->for($candidate, 'notable')->create();
    $document = Document::factory()->for($candidate, 'documentable')->create();

    expect($candidate->notes()->count())->toBe(1)
        ->and($candidate->documents()->count())->toBe(1)
        ->and($note->notable->is($candidate))->toBeTrue()
        ->and($document->documentable->is($candidate))->toBeTrue();
});

it('relie le matching à son besoin et à son candidat', function () {
    $matching = Matching::factory()->create();

    expect($matching->need)->toBeInstanceOf(Need::class)
        ->and($matching->candidate)->toBeInstanceOf(Candidate::class);
});

it('relie une entreprise à ses contacts, besoins et OPCO', function () {
    $company = Company::factory()->create();
    CompanyContact::factory()->count(2)->create(['company_id' => $company->id]);
    Need::factory()->create(['company_id' => $company->id]);

    expect($company->contacts()->count())->toBe(2)
        ->and($company->needs()->count())->toBe(1)
        ->and($company->opco)->toBeInstanceOf(Opco::class);
});

it('relie le contrat à son dossier OPCO et à ses parties', function () {
    // Contrat signé : condition (déterministe) de création du dossier OPCO.
    $contract = Contract::factory()->create([
        'statut_signature' => \App\Enums\ContractSignatureStatut::Signe,
        'statut_contrat' => \App\Enums\ContractStatut::Signe,
    ]);
    $opcoFile = OpcoFile::factory()->create(['contract_id' => $contract->id]);

    expect($opcoFile->contract->is($contract))->toBeTrue()
        ->and($contract->candidate)->toBeInstanceOf(Candidate::class)
        ->and($contract->company)->toBeInstanceOf(Company::class)
        ->and($contract->opcoFile->is($opcoFile))->toBeTrue();
});

it('relie une admission à sa checklist et à son candidat', function () {
    $admission = Admission::factory()->create();
    AdmissionChecklistItem::factory()->count(3)->create(['admission_id' => $admission->id]);

    expect($admission->items()->count())->toBe(3)
        ->and($admission->candidate)->toBeInstanceOf(Candidate::class);
});
