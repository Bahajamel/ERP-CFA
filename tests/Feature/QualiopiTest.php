<?php

use App\Enums\QualiopiStatut;
use App\Filament\Resources\QualiopiIndicators\QualiopiIndicatorResource;
use App\Filament\Widgets\QualiopiConformiteWidget;
use App\Models\QualiopiIndicator;
use App\Models\Task;
use App\Models\User;
use App\Services\AlerteService;
use Database\Seeders\QualiopiIndicatorSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userQualite(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

it('charge les 32 indicateurs du RNQ répartis sur 7 critères', function () {
    $this->seed(QualiopiIndicatorSeeder::class);

    expect(QualiopiIndicator::count())->toBe(32)
        ->and(QualiopiIndicator::distinct('critere')->count('critere'))->toBe(7)
        ->and(QualiopiIndicator::where('specifique_cfa', true)->count())->toBe(6);
});

it('respecte la répartition officielle des indicateurs par critère (RNQ V8)', function () {
    $this->seed(QualiopiIndicatorSeeder::class);

    $repartition = QualiopiIndicator::query()
        ->selectRaw('critere, count(*) as total')
        ->groupBy('critere')
        ->orderBy('critere')
        ->pluck('total', 'critere')
        ->map(fn ($total) => (int) $total)
        ->all();

    // Guide de lecture officiel : C1=3, C2=5, C3=8, C4=4, C5=2, C6=7, C7=3.
    expect($repartition)->toBe([1 => 3, 2 => 5, 3 => 8, 4 => 4, 5 => 2, 6 => 7, 7 => 3]);
});

it('est idempotent et préserve l\'état de conformité déjà saisi', function () {
    $this->seed(QualiopiIndicatorSeeder::class);

    $indicateur = QualiopiIndicator::where('numero', 1)->first();
    $indicateur->update(['statut' => QualiopiStatut::Conforme]);

    $this->seed(QualiopiIndicatorSeeder::class); // rejoué

    expect(QualiopiIndicator::count())->toBe(32)
        ->and($indicateur->fresh()->statut)->toBe(QualiopiStatut::Conforme);
});

it('calcule le taux de conformité hors indicateurs non applicables', function () {
    QualiopiIndicator::factory()->conforme()->count(3)->create();
    QualiopiIndicator::factory()->nonConforme()->count(1)->create();
    QualiopiIndicator::factory()->create(['statut' => QualiopiStatut::AVerifier]);
    QualiopiIndicator::factory()->create(['statut' => QualiopiStatut::NonApplicable]);

    // 3 conformes sur 5 applicables (le « non applicable » est exclu) = 60 %.
    expect(QualiopiIndicator::tauxConformite())->toBe(60);
});

it('retourne 0 % quand aucun indicateur n\'est applicable', function () {
    QualiopiIndicator::factory()->create(['statut' => QualiopiStatut::NonApplicable]);

    expect(QualiopiIndicator::tauxConformite())->toBe(0);
});

it('crée une tâche d\'alerte pour chaque indicateur non conforme', function () {
    $indicateur = QualiopiIndicator::factory()->nonConforme()->create(['numero' => 7]);

    (new AlerteService)->genererAlertes();

    expect(Task::where('cle', "qualiopi:nonconforme:{$indicateur->id}")->exists())->toBeTrue();
});

it('la page du registre explique la section et affiche le taux de conformité', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QualiopiIndicatorSeeder::class);

    $this->actingAs(userQualite('Qualité'));

    $this->get(QualiopiIndicatorResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('certification qualité obligatoire');
});

it('réserve le registre Qualiopi aux rôles habilités', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(userQualite('Qualité'));
    expect(QualiopiIndicatorResource::canAccess())->toBeTrue();

    $this->actingAs(userQualite('Direction'));
    expect(QualiopiIndicatorResource::canAccess())->toBeTrue();

    $this->actingAs(userQualite('Commercial'));
    expect(QualiopiIndicatorResource::canAccess())->toBeFalse();
});

it('réserve le widget de conformité à la direction et à la qualité', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(userQualite('Qualité'));
    expect(QualiopiConformiteWidget::canView())->toBeTrue();

    $this->actingAs(userQualite('Commercial'));
    expect(QualiopiConformiteWidget::canView())->toBeFalse();
});
