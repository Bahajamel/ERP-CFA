<?php

use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\RuptureCaseResource;
use App\Filament\Widgets\DossiersRuptureStats;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FinanceLine;
use App\Models\OpcoFile;
use App\Models\RuptureCase;
use App\Models\Task;
use App\Models\User;
use App\Services\RuptureService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Contrat actif (engagé) : point de départ d'une rupture. */
function contratActif(array $attrs = []): Contract
{
    return Contract::factory()->create(array_merge([
        'statut_contrat' => ContractStatut::Actif,
    ], $attrs));
}

function roleUser(string $role): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles([$role]);

    return $u;
}

it('ouvre un dossier de rupture et passe le contrat à « Rompu »', function () {
    $contract = contratActif();

    $dossier = (new RuptureService)->ouvrir($contract, [
        'motif' => RuptureMotif::Abandon,
        'initiateur' => RuptureInitiateur::Apprenti,
        'date_rupture' => now()->toDateString(),
    ]);

    expect($dossier->statut)->toBe(RuptureStatut::Ouvert)
        ->and($dossier->motif)->toBe(RuptureMotif::Abandon)
        ->and($dossier->initiateur)->toBe(RuptureInitiateur::Apprenti)
        ->and($contract->fresh()->statut_contrat)->toBe(ContractStatut::Rompu);
});

it('est idempotent : deux ouvertures ne créent qu\'un seul dossier', function () {
    $contract = contratActif();
    $service = new RuptureService;

    $service->ouvrir($contract, ['motif' => RuptureMotif::CommunAccord]);
    $service->ouvrir($contract->fresh(), ['motif' => RuptureMotif::Abandon]);

    expect(RuptureCase::where('contract_id', $contract->id)->count())->toBe(1);
});

it('trace une régularisation OPCO quand un dossier OPCO est en cours', function () {
    $contract = contratActif();
    OpcoFile::factory()->create(['contract_id' => $contract->id, 'statut' => OpcoStatut::Depose]);

    (new RuptureService)->ouvrir($contract);

    expect(Task::where('cle', "rupture:opco:{$contract->id}")->exists())->toBeTrue();
});

it('ne trace pas de régularisation OPCO si le dossier OPCO est clôturé', function () {
    $contract = contratActif();
    OpcoFile::factory()->create(['contract_id' => $contract->id, 'statut' => OpcoStatut::Cloture]);

    (new RuptureService)->ouvrir($contract);

    expect(Task::where('cle', "rupture:opco:{$contract->id}")->exists())->toBeFalse();
});

it('trace une régularisation Finance quand des lignes financières existent', function () {
    $contract = contratActif();
    FinanceLine::factory()->create(['contract_id' => $contract->id]);

    (new RuptureService)->ouvrir($contract);

    expect(Task::where('cle', "rupture:finance:{$contract->id}")->exists())->toBeTrue();
});

it('crée un dossier brouillon quand le contrat passe à « Rompu » par simple changement de statut', function () {
    $contract = contratActif();

    $contract->transitionTo(ContractStatut::Rompu);

    expect($contract->ruptureCase()->exists())->toBeTrue()
        ->and($contract->ruptureCase->motif)->toBe(RuptureMotif::Autre);
});

it('suit la machine à états ouvert → accompagnement → reclassé → clos et horodate la clôture', function () {
    $dossier = RuptureCase::factory()->create(['statut' => RuptureStatut::Ouvert]);

    $dossier->transitionTo(RuptureStatut::EnAccompagnement);
    expect($dossier->fresh()->recherche_employeur)->toBeTrue();

    $dossier->transitionTo(RuptureStatut::Reclasse);
    expect($dossier->fresh()->statut)->toBe(RuptureStatut::Reclasse);

    $dossier->transitionTo(RuptureStatut::Clos);
    expect($dossier->fresh()->statut)->toBe(RuptureStatut::Clos)
        ->and($dossier->fresh()->date_cloture)->not->toBeNull();
});

it('journalise les notes d\'accompagnement lors des transitions', function () {
    $dossier = RuptureCase::factory()->create(['statut' => RuptureStatut::Ouvert]);

    $dossier->transitionTo(RuptureStatut::EnAccompagnement, 'Entretien réalisé, piste chez Dupont SARL.');

    expect($dossier->fresh()->accompagnement)->toContain('Entretien réalisé');
});

it('refuse une transition invalide du dossier (ouvert → reclassé direct)', function () {
    $dossier = RuptureCase::factory()->create(['statut' => RuptureStatut::Ouvert]);

    expect($dossier->canTransitionTo(RuptureStatut::Reclasse))->toBeFalse();
});

it('compte les reclassements pour le taux de reclassement', function () {
    $company = Company::factory()->create();
    RuptureCase::factory()->create(['statut' => RuptureStatut::Clos, 'nouvelle_company_id' => $company->id]);
    RuptureCase::factory()->create(['statut' => RuptureStatut::Ouvert]);

    expect(RuptureCase::whereNotNull('nouvelle_company_id')->count())->toBe(1)
        ->and(RuptureCase::whereIn('statut', RuptureStatut::ouverts())->count())->toBe(1);
});

it('réserve le module ruptures à la pédagogie et à l\'administratif, pas au commercial', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(roleUser('Pédagogie'));
    expect(RuptureCaseResource::canAccess())->toBeTrue();

    $this->actingAs(roleUser('Administratif'));
    expect(RuptureCaseResource::canAccess())->toBeTrue();

    $this->actingAs(roleUser('Commercial'));
    expect(RuptureCaseResource::canAccess())->toBeFalse();
});

it('réserve le widget ruptures aux rôles de pilotage', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(roleUser('Direction'));
    expect(DossiersRuptureStats::canView())->toBeTrue();

    $this->actingAs(roleUser('Formateur'));
    expect(DossiersRuptureStats::canView())->toBeFalse();
});

it('rend les pages Filament du module (liste, création, édition) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(roleUser('Administratif'));

    $dossier = RuptureCase::factory()->create(['statut' => RuptureStatut::Ouvert]);

    Livewire\Livewire::test(App\Filament\Resources\Ruptures\Pages\ListRuptureCases::class)
        ->assertOk();
    Livewire\Livewire::test(App\Filament\Resources\Ruptures\Pages\CreateRuptureCase::class)
        ->assertOk();
    Livewire\Livewire::test(App\Filament\Resources\Ruptures\Pages\EditRuptureCase::class, ['record' => $dossier->getKey()])
        ->assertOk();
});
