<?php

use App\Enums\DocumentType;
use App\Enums\EvaluationType;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Models\Candidate;
use App\Models\Evaluation;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use App\Scolarite\BulletinGenerator;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/** Une classe avec N apprenants. */
function classeAvecApprenants(int $nb = 3): array
{
    $formation = Formation::factory()->create(['libelle' => 'BTS SIO', 'matieres' => ['Développement web', 'Anglais professionnel']]);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année', 'annee_scolaire' => '2025-2026']);
    $apprenants = Candidate::factory()->count($nb)->create(['formation_visee_id' => $formation->id]);
    $classe->apprentis()->attach($apprenants->pluck('id'));

    return [$formation, $classe, $apprenants];
}

it('calcule la moyenne d\'une matière pondérée par coefficient (sur 20)', function () {
    $candidate = Candidate::factory()->create();
    Evaluation::factory()->create(['candidate_id' => $candidate->id, 'matiere' => 'Maths', 'note' => 16, 'bareme' => 20, 'coefficient' => 2]);
    Evaluation::factory()->create(['candidate_id' => $candidate->id, 'matiere' => 'Maths', 'note' => 10, 'bareme' => 20, 'coefficient' => 1]);

    $maths = collect($candidate->moyennesParMatiere())->firstWhere('matiere', 'Maths');

    // (16*2 + 10*1) / 3 = 14
    expect($maths['moyenne'])->toBe(14.0)
        ->and($maths['coefficient'])->toBe(3.0)
        ->and($maths['nb'])->toBe(2);
});

it('ramène la note sur 20 selon le barème', function () {
    $eval = Evaluation::factory()->create(['note' => 30, 'bareme' => 40]);

    expect($eval->noteSur20())->toBe(15.0); // 30/40 * 20
});

it('calcule la moyenne générale (moyenne des moyennes de matières)', function () {
    $candidate = Candidate::factory()->create();
    Evaluation::factory()->create(['candidate_id' => $candidate->id, 'matiere' => 'Maths', 'note' => 16, 'bareme' => 20, 'coefficient' => 1]);
    Evaluation::factory()->create(['candidate_id' => $candidate->id, 'matiere' => 'Anglais', 'note' => 12, 'bareme' => 20, 'coefficient' => 1]);

    expect($candidate->moyenneGenerale())->toBe(14.0)  // (16 + 12) / 2
        ->and(Candidate::factory()->create()->moyenneGenerale())->toBeNull(); // aucune note
});

it('crée une note via le formulaire (auteur tracé)', function () {
    [, $classe, $apprenants] = classeAvecApprenants();

    Livewire::test(CreateEvaluation::class)
        ->fillForm([
            'promotion_id' => $classe->id,
            'candidate_id' => $apprenants->first()->id,
            'matiere' => 'Développement web',
            'type' => EvaluationType::Examen->value,
            'note' => 15,
            'bareme' => 20,
            'coefficient' => 2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $eval = Evaluation::first();
    expect($eval)->not->toBeNull()
        ->and($eval->matiere)->toBe('Développement web')
        ->and((float) $eval->note)->toBe(15.0)
        ->and($eval->author_id)->not->toBeNull();
});

it('saisit les notes de toute une classe d\'un coup', function () {
    [, $classe, $apprenants] = classeAvecApprenants(3);
    [$a, $b, $c] = $apprenants;

    Livewire::test(ListEvaluations::class)
        ->callAction('saisirClasse', data: [
            'promotion_id' => $classe->id,
            'matiere' => 'Développement web',
            'type' => EvaluationType::Controle->value,
            'bareme' => 20,
            'coefficient' => 1,
            'note_'.$a->id => 15,
            'note_'.$b->id => 12,
            // $c laissé vide → ignoré
        ])
        ->assertHasNoActionErrors();

    expect(Evaluation::count())->toBe(2)
        ->and(Evaluation::where('candidate_id', $a->id)->value('note'))->toEqual(15)
        ->and(Evaluation::where('candidate_id', $c->id)->exists())->toBeFalse();
});

it('génère le bulletin PDF et l\'archive en GED', function () {
    [, $classe, $apprenants] = classeAvecApprenants(1);
    $apprenant = $apprenants->first();
    Evaluation::factory()->create(['candidate_id' => $apprenant->id, 'promotion_id' => $classe->id, 'matiere' => 'Développement web', 'note' => 16, 'bareme' => 20, 'coefficient' => 1]);

    // Le PDF est un vrai PDF non vide.
    $pdf = app(BulletinGenerator::class)->pdf($apprenant->fresh());
    expect($pdf)->toStartWith('%PDF');

    // L'archivage crée un document GED typé « Bulletin » avec le fichier.
    $document = app(BulletinGenerator::class)->archiver($apprenant->fresh());
    expect($document->type)->toBe(DocumentType::Bulletin)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($apprenant->documents()->where('type', DocumentType::Bulletin->value)->count())->toBe(1);
});

it('importe un bulletin externe en GED, versionné après un bulletin généré', function () {
    [, $classe, $apprenants] = classeAvecApprenants(1);
    $apprenant = $apprenants->first();
    Evaluation::factory()->create(['candidate_id' => $apprenant->id, 'promotion_id' => $classe->id, 'matiere' => 'Développement web', 'note' => 15]);

    // v1 : bulletin généré.
    app(BulletinGenerator::class)->archiver($apprenant->fresh());

    // v2 : bulletin importé (fichier déposé sur le disque public).
    $chemin = \Illuminate\Http\UploadedFile::fake()
        ->createWithContent('officiel.pdf', "%PDF-1.4\nbulletin importé\n%%EOF")
        ->store('imports', 'public');

    $document = app(BulletinGenerator::class)->importer($apprenant->fresh(), $chemin, 'officiel.pdf');

    expect($document->type)->toBe(DocumentType::Bulletin)
        ->and($document->nom_fichier)->toContain('importé')
        ->and($document->version)->toBe(2)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($apprenant->documents()->where('type', DocumentType::Bulletin->value)->count())->toBe(2);
});

it('ouvre le pop-up scolarité avec les actions bulletin (fiche apprenant)', function () {
    [, $classe, $apprenants] = classeAvecApprenants(1);
    $apprenant = $apprenants->first();
    Evaluation::factory()->create(['candidate_id' => $apprenant->id, 'promotion_id' => $classe->id, 'matiere' => 'Développement web', 'note' => 14]);

    // Le pop-up (avec ses actions de pied : ajouter note, bulletin, import) se monte sans erreur.
    Livewire::test(\App\Filament\Resources\Promotions\RelationManagers\ApprentisRelationManager::class, [
        'ownerRecord' => $classe,
        'pageClass' => \App\Filament\Resources\Promotions\Pages\EditPromotion::class,
    ])
        ->mountAction(TestAction::make('ficheApprenant')->table($apprenant))
        ->assertActionMounted(TestAction::make('ficheApprenant')->table($apprenant));
});
