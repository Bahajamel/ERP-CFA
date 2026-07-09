<?php

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

/** Une séance avec sa classe et N apprenants prévus (présences générées). */
function seanceAvecApprenants(int $nb = 2): array
{
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenants = Candidate::factory()->count($nb)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    $seance = Seance::factory()->create([
        'promotion_id' => $classe->id,
        'libelle' => 'Développement web',
        'date' => now()->startOfWeek()->addDay()->toDateString(),
    ]);

    return [$classe, $seance, $apprenants];
}

it('ouvre l\'aperçu d\'une séance en pop-up depuis l\'emploi du temps', function () {
    [$classe, $seance] = seanceAvecApprenants();

    Livewire::test(EmploiDuTemps::class, ['promotionId' => $classe->id])
        ->mountAction(TestAction::make('voirSeance')->arguments(['seance' => $seance->id]))
        ->assertActionMounted(TestAction::make('voirSeance')->arguments(['seance' => $seance->id]));
});

it('affiche les infos et la liste des apprenants prévus (sans émargement)', function () {
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenant = Candidate::factory()->create(['nom' => 'Okonkwo', 'prenom' => 'Amara', 'formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenant->id);
    $seance = Seance::factory()->create(['promotion_id' => $classe->id, 'libelle' => 'Développement web']);

    $html = view('filament.seance-apercu', [
        'seance' => $seance->load(['promotion.formation', 'formateur', 'presences.candidate']),
    ])->render();

    expect($html)
        ->toContain('Développement web')                 // matière (titre du bandeau)
        ->toContain(e($seance->promotion->nom_complet))  // classe complète (Blade échappe l'apostrophe)
        ->toContain('Date')
        ->toContain('Horaires')
        ->toContain('Formateur')
        ->toContain('Apprenants prévus')              // section liste
        ->toContain('Amara Okonkwo')                  // nom + prénom listés
        ->not->toContain('Enregistrer l\'émargement'); // pas d'émargement dans le pop-up
});

it('liste tous les apprenants d\'un grand effectif dans la zone défilante', function () {
    [$classe, $seance] = seanceAvecApprenants(30);

    $html = view('filament.seance-apercu', [
        'seance' => $seance->load(['promotion.formation', 'formateur', 'presences.candidate']),
    ])->render();

    // Les 30 apprenants sont tous rendus, dans un conteneur à défilement (max-height).
    $noms = Candidate::whereHas('promotions', fn ($q) => $q->whereKey($classe->id))->get();
    expect($noms)->toHaveCount(30);

    foreach ($noms as $c) {
        expect($html)->toContain(trim($c->prenom.' '.$c->nom));
    }

    expect($html)->toContain('max-height'); // la liste défile plutôt que d'agrandir le pop-up
});
