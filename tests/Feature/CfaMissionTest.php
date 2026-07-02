<?php

use App\Filament\Resources\CfaMissions\CfaMissionResource;
use App\Filament\Widgets\CfaMissionsCouvertureWidget;
use App\Models\CfaMission;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\CfaMissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userDocPourMission(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

it('charge les 14 missions du CFA numérotées de 1 à 14 (L6231-2)', function () {
    $this->seed(CfaMissionSeeder::class);

    expect(CfaMission::count())->toBe(14)
        ->and(CfaMission::pluck('numero')->sort()->values()->all())->toBe(range(1, 14))
        ->and(CfaMission::where('reference', 'L6231-2')->count())->toBe(14);
});

it('reproduit le texte officiel de certaines missions (vérif verbatim)', function () {
    $this->seed(CfaMissionSeeder::class);

    // Mission 1 : la désignation d'un référent handicap est dans le texte officiel.
    expect(CfaMission::where('numero', 1)->value('texte'))
        ->toContain("référent chargé de l'intégration des personnes en situation de handicap")
        // Mission 5 : la poursuite de formation « pendant six mois » en cas de rupture.
        ->and(CfaMission::where('numero', 5)->value('texte'))->toContain('six mois')
        // Mission 12 : évaluation des compétences / organisme certificateur.
        ->and(CfaMission::where('numero', 12)->value('texte'))->toContain('organisme certificateur');
});

it('ne contient aucune mission « concours » (erreur du référentiel LivretRS)', function () {
    $this->seed(CfaMissionSeeder::class);

    expect(CfaMission::where('code', 'concours')->exists())->toBeFalse();
});

it('est idempotent : rejouer le seeder ne duplique pas', function () {
    $this->seed(CfaMissionSeeder::class);
    $this->seed(CfaMissionSeeder::class);

    expect(CfaMission::count())->toBe(14);
});

it('rattache un document à plusieurs missions (relation N-N)', function () {
    $this->seed(CfaMissionSeeder::class);

    $document = Document::factory()->create();
    $missions = CfaMission::whereIn('numero', [4, 14])->pluck('id'); // missions 4 et 14
    $document->missions()->attach($missions);

    expect($document->missions()->count())->toBe(2)
        ->and(CfaMission::where('numero', 4)->first()->documents()->count())->toBe(1);
});

it('calcule le taux de couverture des missions', function () {
    $this->seed(CfaMissionSeeder::class);

    // 7 missions couvertes sur 14 => 50 %.
    CfaMission::query()->orderBy('numero')->limit(7)->get()->each(function (CfaMission $mission) {
        $mission->documents()->attach(Document::factory()->create());
    });

    expect(CfaMission::tauxCouverture())->toBe(50);
});

it('renvoie 0 % de couverture quand aucun livrable n\'est rattaché', function () {
    $this->seed(CfaMissionSeeder::class);

    expect(CfaMission::tauxCouverture())->toBe(0);
});

it('réserve le registre des missions aux rôles ayant accès aux documents', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(userDocPourMission('Qualité'));
    expect(CfaMissionResource::canAccess())->toBeTrue();

    $this->actingAs(userDocPourMission('Direction'));
    expect(CfaMissionResource::canAccess())->toBeTrue();

    $this->actingAs(userDocPourMission('Commercial'));
    expect(CfaMissionResource::canAccess())->toBeFalse();
});

it('réserve le widget de couverture aux rôles habilités', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->actingAs(userDocPourMission('Qualité'));
    expect(CfaMissionsCouvertureWidget::canView())->toBeTrue();

    $this->actingAs(userDocPourMission('Commercial'));
    expect(CfaMissionsCouvertureWidget::canView())->toBeFalse();
});

it('la page du registre explique la section et liste les 14 missions', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CfaMissionSeeder::class);

    $this->actingAs(userDocPourMission('Qualité'));

    $this->get(CfaMissionResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('14 missions')
        ->assertSee('L6231-2');
});
