<?php

use App\Enums\DocumentType;
use App\Filament\Resources\Seances\Pages\ListSeances;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake(config('media-library.disk_name', 'public'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);
});

function scanFeuille(string $nom): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nom, "%PDF-1.4\nfeuille émargée\n%%EOF");
}

it('archive le scan de la feuille d\'émargement dans la GED', function () {
    $seance = Seance::factory()->create();

    Livewire::test(ListSeances::class)
        ->callAction(TestAction::make('feuilleEmargement')->table($seance), data: [
            'fichier' => scanFeuille('emargement-01-07.pdf'),
        ])
        ->assertHasNoActionErrors();

    $feuille = $seance->feuilleEmargement();

    expect($feuille)->not->toBeNull()
        ->and($feuille->type)->toBe(DocumentType::FeuilleEmargement)
        ->and($feuille->nom_fichier)->toBe('emargement-01-07.pdf')
        ->and($feuille->version)->toBe(1)
        ->and($feuille->getFirstMedia('fichier'))->not->toBeNull();
});

it('crée une nouvelle version à chaque dépôt sans effacer la précédente', function () {
    $seance = Seance::factory()->create();

    foreach (['scan-v1.pdf', 'scan-v2.pdf'] as $nom) {
        Livewire::test(ListSeances::class)
            ->callAction(TestAction::make('feuilleEmargement')->table($seance), data: [
                'fichier' => scanFeuille($nom),
            ])
            ->assertHasNoActionErrors();
    }

    $courante = $seance->feuilleEmargement();

    expect($seance->documents()->where('type', DocumentType::FeuilleEmargement)->count())->toBe(2)
        ->and($courante->version)->toBe(2)
        ->and($courante->nom_fichier)->toBe('scan-v2.pdf')
        ->and($courante->previousVersion?->nom_fichier)->toBe('scan-v1.pdf');
});

it('dépose la feuille depuis la section Émargement (près des apprenants)', function () {
    $seance = Seance::factory()->create();

    Livewire::test(\App\Filament\Resources\Seances\RelationManagers\PresencesRelationManager::class, [
        'ownerRecord' => $seance,
        'pageClass' => \App\Filament\Resources\Seances\Pages\EditSeance::class,
    ])
        ->callAction(TestAction::make('feuilleEmargement')->table(), data: [
            'fichier' => scanFeuille('emargement-depuis-emargement.pdf'),
        ])
        ->assertHasNoActionErrors()
        // L'événement synchronise le bouton de l'autre composant (page ↔ section).
        ->assertDispatched('feuille-emargement-maj');

    expect($seance->feuilleEmargement()?->nom_fichier)->toBe('emargement-depuis-emargement.pdf');
});

it('filtre les séances par formation et par année', function () {
    $cda = \App\Models\Formation::factory()->create(['libelle' => 'CDA']);
    $bts = \App\Models\Formation::factory()->create(['libelle' => 'BTS MCO']);

    $cda1 = \App\Models\Promotion::factory()->create(['formation_id' => $cda->id, 'libelle' => '1ère année']);
    $cda2 = \App\Models\Promotion::factory()->create(['formation_id' => $cda->id, 'libelle' => '2ème année']);
    $bts1 = \App\Models\Promotion::factory()->create(['formation_id' => $bts->id, 'libelle' => '1ère année']);

    Seance::factory()->create(['promotion_id' => $cda1->id, 'libelle' => 'SeanceCda1']);
    Seance::factory()->create(['promotion_id' => $cda2->id, 'libelle' => 'SeanceCda2']);
    Seance::factory()->create(['promotion_id' => $bts1->id, 'libelle' => 'SeanceBts1']);

    // Formation seule : toutes les séances CDA, pas celles du BTS.
    Livewire::test(ListSeances::class)
        ->filterTable('formation', $cda->id)
        ->assertCanSeeTableRecords(Seance::whereIn('promotion_id', [$cda1->id, $cda2->id])->get())
        ->assertCanNotSeeTableRecords(Seance::where('promotion_id', $bts1->id)->get());

    // Formation + année : seulement la cohorte 1ère année CDA.
    Livewire::test(ListSeances::class)
        ->filterTable('formation', $cda->id)
        ->filterTable('annee', '1ère année')
        ->assertCanSeeTableRecords(Seance::where('promotion_id', $cda1->id)->get())
        ->assertCanNotSeeTableRecords(Seance::whereIn('promotion_id', [$cda2->id, $bts1->id])->get());
});

it('affiche la liste des séances groupée par formation, comme la liste des classes', function () {
    $formation = \App\Models\Formation::factory()->create(['libelle' => 'BTS Groupement Séances']);
    $classe = \App\Models\Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    Seance::factory()->create(['promotion_id' => $classe->id, 'libelle' => 'MatiereGroupee']);

    Livewire::test(ListSeances::class)
        ->assertSuccessful()
        ->assertSee('BTS Groupement Séances') // en-tête du groupe
        ->assertSee('MatiereGroupee');
});

it('reste consultable sans nouveau dépôt (aucun document créé)', function () {
    $seance = Seance::factory()->create();

    Livewire::test(ListSeances::class)
        ->callAction(TestAction::make('feuilleEmargement')->table($seance))
        ->assertHasNoActionErrors();

    expect($seance->documents()->count())->toBe(0);
});
