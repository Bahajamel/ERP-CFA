<?php

use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Filament\Resources\Seances\Pages\ListSeances;
use App\Filament\Widgets\SeancesStatsOverview;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
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

function seanceAvec(int $n = 2, array $attrs = []): Seance
{
    $formation = Formation::factory()->create();
    $promotion = Promotion::factory()->create(['formation_id' => $formation->id]);
    $apprentis = Candidate::factory()->count($n)->create();
    $promotion->apprentis()->attach($apprentis->pluck('id'));

    return Seance::factory()->create(array_merge(['promotion_id' => $promotion->id], $attrs));
}

it('affiche la liste des séances et les cartes KPI', function () {
    $seance = seanceAvec(2, ['date' => today()]);

    Livewire::test(ListSeances::class)
        ->assertOk()
        ->assertSee($seance->promotion->formation->libelle);

    Livewire::test(SeancesStatsOverview::class)
        ->assertOk()
        ->assertSee('Taux de présence')
        ->assertSee('Signatures en attente');
});

it('dérive le statut d\'affichage selon la date et les présences', function () {
    expect(seanceAvec(1, ['date' => now()->addWeek(), 'statut' => SeanceStatut::Planifiee])->statutAffiche()['label'])
        ->toBe('Planifiée');

    expect(seanceAvec(1, ['date' => today(), 'statut' => SeanceStatut::Planifiee])->statutAffiche()['label'])
        ->toBe('En cours');

    $passe = seanceAvec(1, ['date' => now()->subWeek(), 'statut' => SeanceStatut::Planifiee]);
    expect($passe->statutAffiche()['label'])->toBe('À compléter'); // présence non renseignée

    $passe->presences()->update(['statut' => PresenceStatut::Present->value]);
    expect($passe->fresh()->statutAffiche()['label'])->toBe('À valider'); // complète

    expect(seanceAvec(1, ['statut' => SeanceStatut::Validee])->statutAffiche()['label'])->toBe('Validée');
    expect(seanceAvec(1, ['statut' => SeanceStatut::Annulee])->statutAffiche()['label'])->toBe('Annulée');
});

it('dérive l\'état d\'émargement', function () {
    $seance = seanceAvec(2, ['date' => today()]);
    expect($seance->etatEmargement()['label'])->toBe('À ouvrir');

    $seance->presences()->first()->update(['signed_at' => now()]);
    expect($seance->fresh()->etatEmargement()['label'])->toContain('Signé');
});
