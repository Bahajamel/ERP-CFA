<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\EmploiDuTemps;
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

/** Une séance émargeable (classe + 2 apprenants + présences générées). */
function seanceEmargeable(): array
{
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenants = Candidate::factory()->count(2)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    $seance = Seance::factory()->create([
        'promotion_id' => $classe->id,
        'libelle' => 'Développement web',
        'date' => now()->startOfWeek()->addDay()->toDateString(),
    ]);

    return [$classe, $seance, $apprenants];
}

it('ouvre l\'aperçu d\'une séance en pop-up depuis l\'emploi du temps', function () {
    [$classe, $seance] = seanceEmargeable();

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->mountAction(TestAction::make('voirSeance')->arguments(['seance' => $seance->id]))
        ->assertActionMounted(TestAction::make('voirSeance')->arguments(['seance' => $seance->id]));
});

it('affiche un panneau d\'informations clair dans le pop-up', function () {
    [, $seance] = seanceEmargeable();

    $html = view('filament.seance-apercu', [
        'seance' => $seance->load(['promotion.formation', 'formateur', 'presences.candidate']),
    ])->render();

    expect($html)
        ->toContain('Développement web')            // matière (titre du bandeau)
        ->toContain(e($seance->promotion->nom_complet)) // classe complète (Blade échappe l'apostrophe)
        ->toContain('Date')
        ->toContain('Horaires')
        ->toContain('Formateur')
        ->toContain('Statut')
        ->toContain('Assiduité')
        ->toContain('2 apprenants');                 // effectif
});

it('gère un grand effectif dans le pop-up (montage + émargement de 30 apprenants)', function () {
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenants = Candidate::factory()->count(30)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    $seance = Seance::factory()->create([
        'promotion_id' => $classe->id,
        'libelle' => 'Cours magistral',
        'date' => now()->startOfWeek()->addDay()->toDateString(),
    ]);

    $presences = $seance->presences()->get();
    expect($presences)->toHaveCount(30);

    // Le pop-up se monte sans erreur avec 30 apprenants, et l'émargement enregistre.
    $data = $presences->mapWithKeys(fn ($p) => ['statut_'.$p->id => PresenceStatut::Present->value])->all();

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->callAction(TestAction::make('voirSeance')->arguments(['seance' => $seance->id]), data: $data)
        ->assertHasNoActionErrors();

    expect($seance->presences()->where('statut', PresenceStatut::Present->value)->count())->toBe(30);
});

it('émarge les apprenants directement dans le pop-up (sans changer de page)', function () {
    [$classe, $seance, $apprenants] = seanceEmargeable();
    $presences = $seance->presences()->orderBy('candidate_id')->get();

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->callAction(
            TestAction::make('voirSeance')->arguments(['seance' => $seance->id]),
            data: [
                'statut_'.$presences[0]->id => PresenceStatut::Present->value,
                'statut_'.$presences[1]->id => PresenceStatut::AbsentJustifie->value,
            ],
        )
        ->assertHasNoActionErrors();

    expect($presences[0]->fresh()->statut)->toBe(PresenceStatut::Present)
        ->and($presences[1]->fresh()->statut)->toBe(PresenceStatut::AbsentJustifie);
});
