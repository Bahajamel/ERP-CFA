<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\EmploiDuTemps;
use App\Filament\Resources\Seances\Pages\CreateSeance;
use App\Filament\Resources\Seances\Pages\EditSeance;
use App\Filament\Resources\Seances\RelationManagers\PresencesRelationManager;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);
});

/** Une cohorte avec 3 apprenants. */
function cohorteAvecApprenants(): array
{
    $formation = Formation::factory()->create(['libelle' => 'CDA Options']);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenants = Candidate::factory()->count(3)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    return [$formation, $classe, $apprenants];
}

it('après création, redirige vers l\'emploi du temps positionné sur la cohorte et la semaine de la séance', function () {
    [$formation, $classe] = cohorteAvecApprenants();
    $dansDeuxSemaines = now()->addWeeks(2)->toDateString();

    Livewire::test(CreateSeance::class)
        ->fillForm([
            'promotion_id' => $classe->id,
            'libelle' => 'MatiereSync',
            'date' => $dansDeuxSemaines,
            'formateur_id' => User::factory()->create()->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(EmploiDuTemps::getUrl([
            'promotion' => $classe->id,
            'semaine' => $dansDeuxSemaines,
        ]));

    // Et l'emploi du temps ainsi positionné affiche bien la nouvelle séance.
    $this->get(EmploiDuTemps::getUrl(['promotion' => $classe->id, 'semaine' => $dansDeuxSemaines]))
        ->assertOk()
        ->assertSee('MatiereSync');
});

it('exige un formateur à la création d\'une séance', function () {
    [, $classe] = cohorteAvecApprenants();

    Livewire::test(CreateSeance::class)
        ->fillForm([
            'promotion_id' => $classe->id,
            'libelle' => 'Séance sans formateur',
            'date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['formateur_id']);

    expect(Seance::where('libelle', 'Séance sans formateur')->exists())->toBeFalse();
});

it('n\'émarge que les apprenants cochés (options différentes au sein de la cohorte)', function () {
    [, $classe, $apprenants] = cohorteAvecApprenants();
    [$suit1, $suit2, $suitPas] = $apprenants;

    Livewire::test(CreateSeance::class)
        ->fillForm([
            'promotion_id' => $classe->id,
            'libelle' => 'Option Anglais renforcé',
            'date' => now()->toDateString(),
            'formateur_id' => User::factory()->create()->id,
            'participants_ids' => [$suit1->id, $suit2->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $seance = Seance::firstWhere('libelle', 'Option Anglais renforcé');

    expect($seance->presences()->pluck('candidate_id')->sort()->values()->all())
        ->toBe(collect([$suit1->id, $suit2->id])->sort()->values()->all());
});

it('gère les participants depuis la section Émargement (liste à cocher par séance)', function () {
    [, $classe, $apprenants] = cohorteAvecApprenants();
    [$garde, $retire, $ajoute] = $apprenants;

    $seance = Seance::factory()->create(['promotion_id' => $classe->id, 'libelle' => 'Option Web']);

    Livewire::test(PresencesRelationManager::class, [
        'ownerRecord' => $seance,
        'pageClass' => EditSeance::class,
    ])
        ->callAction(
            TestAction::make('gererParticipants')->table(),
            data: ['participants_ids' => [$garde->id, $ajoute->id]],
        )
        ->assertHasNoActionErrors();

    expect($seance->presences()->pluck('candidate_id')->sort()->values()->all())
        ->toBe(collect([$garde->id, $ajoute->id])->sort()->values()->all())
        ->and($retire->presences()->where('seance_id', $seance->id)->exists())->toBeFalse();
});

it('synchronise l\'émargement quand on modifie les participants à l\'édition', function () {
    [, $classe, $apprenants] = cohorteAvecApprenants();
    [$garde, $retire, $ajoute] = $apprenants;

    $seance = Seance::factory()->create(['promotion_id' => $classe->id, 'libelle' => 'Option Data']);
    // Généré automatiquement : les 3 apprenants. On retire le 2e, on garde le 1er et le 3e.
    $retire->presences()->where('seance_id', $seance->id)->first()?->update(['statut' => PresenceStatut::Present]);

    Livewire::test(EditSeance::class, ['record' => $seance->getRouteKey()])
        ->fillForm(['participants_ids' => [$garde->id, $ajoute->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($seance->presences()->pluck('candidate_id')->sort()->values()->all())
        ->toBe(collect([$garde->id, $ajoute->id])->sort()->values()->all());
});
