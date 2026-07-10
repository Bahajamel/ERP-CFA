<?php

use App\Enums\EvaluationType;
use App\Filament\Pages\Notes;
use App\Models\Candidate;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
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

function classeNotee(int $nb = 3): array
{
    $formation = Formation::factory()->create([
        'libelle' => 'BTS SIO',
        'matieres' => ['Développement web', 'Cybersécurité', 'Anglais professionnel'],
    ]);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenants = Candidate::factory()->count($nb)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    return [$formation, $classe, $apprenants];
}

it('affiche les matières du programme après avoir choisi une classe', function () {
    [, $classe] = classeNotee();

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->assertSuccessful()
        ->assertSee($classe->nom_complet)
        ->assertSee('Développement web')
        ->assertSee('Cybersécurité')
        ->assertSee('Anglais professionnel');
});

it('affiche les inscrits et leurs notes après avoir choisi une matière', function () {
    [, $classe, $apprenants] = classeNotee(2);
    [$a, $b] = $apprenants;
    Evaluation::factory()->create(['candidate_id' => $a->id, 'promotion_id' => $classe->id, 'matiere' => 'Développement web', 'note' => 16, 'bareme' => 20, 'coefficient' => 1]);

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->assertSee($a->nom_complet)   // inscrit listé
        ->assertSee($b->nom_complet)
        ->assertSee('16')              // note de A
        ->assertSee('Pas encore de note'); // B non noté
});

it('ne mélange pas les notes d\'une autre matière', function () {
    [, $classe, $apprenants] = classeNotee(1);
    $a = $apprenants->first();
    Evaluation::factory()->create(['candidate_id' => $a->id, 'promotion_id' => $classe->id, 'matiere' => 'Cybersécurité', 'note' => 8]);

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->assertSee('Pas encore de note')   // rien en Dév web
        ->assertDontSee('8 /');
});

it('réinitialise la matière quand on change de classe', function () {
    [$formation, $classe] = classeNotee(1);
    $autre = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '2ème année']);

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->assertSet('matiere', 'Développement web')
        ->set('promotionId', $autre->id)
        ->assertSet('matiere', null);
});

it('saisit une épreuve pour toute la classe via l\'action', function () {
    [, $classe, $apprenants] = classeNotee(3);
    [$a, $b, $c] = $apprenants;

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->callAction('nouvelleEpreuve', data: [
            'type' => EvaluationType::Examen->value,
            'bareme' => 20,
            'coefficient' => 2,
            'note_'.$a->id => 15,
            'note_'.$b->id => 11,
            // $c laissé vide
        ])
        ->assertHasNoActionErrors();

    expect(Evaluation::where('matiere', 'Développement web')->count())->toBe(2)
        ->and(Evaluation::where('candidate_id', $a->id)->value('note'))->toEqual(15)
        ->and(Evaluation::where('candidate_id', $c->id)->exists())->toBeFalse();
});
