<?php

use App\Enums\EvaluationType;
use App\Filament\Pages\Notes;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Models\Candidate;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('importe des examens comme preuve pour la matière (GED sur la classe)', function () {
    Storage::fake('public');
    Storage::fake(config('media-library.disk_name', 'public'));
    [, $classe, $apprenants] = classeNotee(1);
    $apprenant = $apprenants->first();

    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->callAction(
            'importerExamenApprenant',
            data: [
                'type_examen' => EvaluationType::Controle->value,
                'fichier' => UploadedFile::fake()->create('copie-eleve.pdf', 120, 'application/pdf'),
            ],
            arguments: ['candidate' => $apprenant->id],
        )
        ->assertHasNoActionErrors();

    // La copie est rattachée à l'APPRENANT, typée Examen, avec matière + classe + type d'épreuve.
    $doc = Document::where('documentable_type', $apprenant->getMorphClass())
        ->where('documentable_id', $apprenant->id)
        ->where('type', 'examen')
        ->first();

    expect($doc)->not->toBeNull()
        ->and($doc->getFirstMedia('fichier'))->not->toBeNull()
        ->and($doc->getFirstMedia('fichier')->getCustomProperty('matiere'))->toBe('Développement web')
        ->and((int) $doc->getFirstMedia('fichier')->getCustomProperty('promotion_id'))->toBe($classe->id)
        ->and($doc->getFirstMedia('fichier')->getCustomProperty('type'))->toBe(EvaluationType::Controle->value);
});

it('n\'affiche la copie d\'un apprenant que pour la matière concernée', function () {
    Storage::fake('public');
    Storage::fake(config('media-library.disk_name', 'public'));
    [, $classe, $apprenants] = classeNotee(1);
    $apprenant = $apprenants->first();

    // Copie importée pour « Développement web », type « Contrôle ».
    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->callAction(
            'importerExamenApprenant',
            data: [
                'type_examen' => EvaluationType::Controle->value,
                'fichier' => UploadedFile::fake()->create('copie.pdf', 60, 'application/pdf'),
            ],
            arguments: ['candidate' => $apprenant->id],
        );

    // Sous « Développement web » : la copie est là, affichée avec son type.
    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->assertSee(EvaluationType::Controle->getLabel());

    // Sous « Cybersécurité » : pas de copie → bouton « Importer ».
    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Cybersécurité')
        ->assertSee('Importer')
        ->assertDontSee(EvaluationType::Controle->getLabel());
});

it('rend chaque note cliquable vers la copie d\'examen de son type', function () {
    Storage::fake('public');
    Storage::fake(config('media-library.disk_name', 'public'));
    [, $classe, $apprenants] = classeNotee(1);
    $apprenant = $apprenants->first();

    // Une note de type « Contrôle » pour l'apprenant.
    Evaluation::factory()->create([
        'candidate_id' => $apprenant->id,
        'promotion_id' => $classe->id,
        'matiere' => 'Développement web',
        'type' => EvaluationType::Controle->value,
        'note' => 14,
    ]);

    // Import de la copie de « Contrôle ».
    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->callAction('importerExamenApprenant', data: [
            'type_examen' => EvaluationType::Controle->value,
            'fichier' => UploadedFile::fake()->create('controle.pdf', 40, 'application/pdf'),
        ], arguments: ['candidate' => $apprenant->id])
        ->assertHasNoActionErrors();

    // Rendu FRAIS : la note pointe désormais vers la copie de son type.
    Livewire::test(Notes::class, ['promotionId' => $classe->id])
        ->call('choisirMatiere', 'Développement web')
        ->assertSee('nt-note--proof');
});

it('ouvre l\'édition d\'une note sans page « liste » (fil d\'Ariane vers le cahier)', function () {
    [, $classe, $apprenants] = classeNotee(1);
    $note = Evaluation::factory()->create([
        'candidate_id' => $apprenants->first()->id,
        'promotion_id' => $classe->id,
        'matiere' => 'Développement web',
        'note' => 12,
    ]);

    // La page d'édition se monte : son fil d'Ariane utilise getIndexUrl(),
    // qui pointe désormais vers le cahier de notes (plus de route « index »).
    Livewire::test(EditEvaluation::class, ['record' => $note->getRouteKey()])
        ->assertSuccessful();

    expect(EvaluationResource::getIndexUrl())
        ->toBe(Notes::getUrl());
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
