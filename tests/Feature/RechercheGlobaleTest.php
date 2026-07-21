<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // La recherche globale respecte désormais les policies : elle exige un
    // utilisateur autorisé (ici un administrateur, qui a toutes les permissions).
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    $admin->syncRoles('Administrateur');
    $this->actingAs($admin);
});

it('trouve un candidat et une entreprise par la recherche globale', function () {
    Candidate::factory()->create(['nom' => 'Zoubairi', 'prenom' => 'Karim']);
    Company::factory()->create(['raison_sociale' => 'Zephyr Industries']);

    expect(CandidateResource::getGlobalSearchResults('Zoubairi'))->not->toBeEmpty()
        ->and(CompanyResource::getGlobalSearchResults('Zephyr'))->not->toBeEmpty();
});

it('trouve un besoin par intitulé de poste', function () {
    Need::factory()->create(['intitule_poste' => 'Soudeur alternant TIG']);

    expect(NeedResource::getGlobalSearchResults('Soudeur TIG'))->not->toBeEmpty();
});

it('trouve un contrat par le nom de l\'apprenti', function () {
    $candidate = Candidate::factory()->create(['nom' => 'Vanderquux', 'prenom' => 'Lise']);
    Contract::factory()->create(['candidate_id' => $candidate->id]);

    $resultats = ContractResource::getGlobalSearchResults('Vanderquux');

    expect($resultats)->not->toBeEmpty()
        ->and($resultats->first()->title)->toContain('Lise Vanderquux');
});

it('trouve un dossier OPCO via l\'apprenti du contrat', function () {
    $candidate = Candidate::factory()->create(['nom' => 'Wexford', 'prenom' => 'Tom']);
    $contract = Contract::factory()->create([
        'candidate_id' => $candidate->id,
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
    OpcoFile::factory()->create(['contract_id' => $contract->id]);

    expect(OpcoFileResource::getGlobalSearchResults('Wexford'))->not->toBeEmpty();
});

it('trouve un document et une tâche', function () {
    Document::factory()->create([
        'type' => DocumentType::Cerfa,
        'nom_fichier' => 'Attestation Qwertz signée',
    ]);
    Task::factory()->create(['titre' => 'Relancer le tuteur Qwertz']);

    expect(DocumentResource::getGlobalSearchResults('Qwertz'))->not->toBeEmpty()
        ->and(TaskResource::getGlobalSearchResults('Qwertz'))->not->toBeEmpty();
});
