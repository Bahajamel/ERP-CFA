<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\RiskLevel;
use App\Filament\Widgets\ApprentisARisqueTable;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\Task;
use App\Models\User;
use App\Services\AlerteService;
use App\Services\RuptureRiskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Contrat « sain » par défaut : signé, tuteur désigné, démarré depuis 3 mois. */
function contratSain(array $attrs = []): Contract
{
    return Contract::factory()->create(array_merge([
        'statut_contrat' => ContractStatut::Signe,
        'statut_signature' => ContractSignatureStatut::Signe,
        'tuteur_id' => CompanyContact::factory(),
        'date_debut' => now()->subMonths(3),
    ], $attrs));
}

it('traduit correctement un score en niveau de risque', function () {
    expect(RiskLevel::fromScore(0))->toBe(RiskLevel::Faible)
        ->and(RiskLevel::fromScore(14))->toBe(RiskLevel::Faible)
        ->and(RiskLevel::fromScore(15))->toBe(RiskLevel::Modere)
        ->and(RiskLevel::fromScore(34))->toBe(RiskLevel::Modere)
        ->and(RiskLevel::fromScore(35))->toBe(RiskLevel::Eleve)
        ->and(RiskLevel::fromScore(59))->toBe(RiskLevel::Eleve)
        ->and(RiskLevel::fromScore(60))->toBe(RiskLevel::Critique)
        ->and(RiskLevel::fromScore(100))->toBe(RiskLevel::Critique);
});

it('ne détecte aucun risque sur un contrat sain', function () {
    $eval = (new RuptureRiskService)->evaluate(contratSain());

    expect($eval->score)->toBe(0)
        ->and($eval->level)->toBe(RiskLevel::Faible)
        ->and($eval->factors)->toBeEmpty();
});

it('détecte l\'absence de maître d\'apprentissage', function () {
    $eval = (new RuptureRiskService)->evaluate(contratSain(['tuteur_id' => null]));

    expect($eval->score)->toBe(25)
        ->and($eval->level)->toBe(RiskLevel::Modere)
        ->and($eval->labels())->toContain('Aucun maître d\'apprentissage désigné');
});

it('détecte un contrat démarré mais non signé', function () {
    $eval = (new RuptureRiskService)->evaluate(contratSain([
        'statut_signature' => ContractSignatureStatut::NonSigne,
    ]));

    expect($eval->labels())->toContain('Contrat démarré mais non signé');
});

it('détecte la période d\'essai', function () {
    $eval = (new RuptureRiskService)->evaluate(contratSain([
        'date_debut' => now()->subDays(10),
    ]));

    expect($eval->labels())->toContain('En période d\'essai (45 j) — rupture possible sans motif');
});

it('détecte un financement OPCO bloqué', function () {
    $contract = contratSain();
    OpcoFile::factory()->create(['contract_id' => $contract->id, 'statut' => OpcoStatut::Rejete]);

    $eval = (new RuptureRiskService)->evaluate($contract->fresh());

    expect($eval->labels())->toContain('Financement OPCO bloqué (rejeté / en correction)');
});

it('cumule les facteurs jusqu\'au niveau critique', function () {
    $eval = (new RuptureRiskService)->evaluate(contratSain([
        'tuteur_id' => null,
        'statut_signature' => ContractSignatureStatut::NonSigne,
        'date_debut' => now()->subDays(10),
    ]));

    // 25 (sans tuteur) + 30 (non signé) + 20 (période d'essai) = 75.
    expect($eval->score)->toBe(75)
        ->and($eval->level)->toBe(RiskLevel::Critique);
});

it('évalue tous les contrats en cours, persiste l\'instantané et compte ceux à risque', function () {
    contratSain(); // Faible, en cours (signé) — non compté.
    $critique = contratSain([
        'tuteur_id' => null,
        'statut_signature' => ContractSignatureStatut::NonSigne,
        'date_debut' => now()->subDays(10),
    ]);
    contratSain(['statut_contrat' => ContractStatut::Rompu]); // hors périmètre.

    $aRisque = (new RuptureRiskService)->evaluerTous();

    expect($aRisque)->toBe(1)
        ->and($critique->fresh()->risk_level)->toBe(RiskLevel::Critique)
        ->and($critique->fresh()->risk_score)->toBe(75)
        ->and($critique->fresh()->risk_factors)->toHaveCount(3);
});

it('crée une tâche d\'alerte pour un contrat à risque élevé', function () {
    $critique = contratSain([
        'tuteur_id' => null,
        'statut_signature' => ContractSignatureStatut::NonSigne,
        'date_debut' => now()->subDays(10),
    ]);

    (new RuptureRiskService)->evaluerTous();
    (new AlerteService)->genererAlertes();

    expect(Task::where('cle', "rupture:risque:{$critique->id}")->exists())->toBeTrue();
});

it('exécute la commande d\'évaluation des risques', function () {
    contratSain(['tuteur_id' => null]);

    $this->artisan('app:evaluer-risques')->assertSuccessful();
});

it('réserve le widget « apprentis à risque » à la direction et à la pédagogie', function () {
    $this->seed(RolePermissionSeeder::class);

    $makeUser = function (string $role) {
        $u = User::factory()->create(['is_active' => true]);
        $u->syncRoles([$role]);

        return $u;
    };

    $this->actingAs($makeUser('Direction'));
    expect(ApprentisARisqueTable::canView())->toBeTrue();

    $this->actingAs($makeUser('Pédagogie'));
    expect(ApprentisARisqueTable::canView())->toBeTrue();

    $this->actingAs($makeUser('Commercial'));
    expect(ApprentisARisqueTable::canView())->toBeFalse();
});
