<?php

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Filament\Pages\Assiduite;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\RelationManagers\ApprentisRelationManager;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
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

/** Un apprenant rattaché à une classe (visible sur la page Assiduité). */
function apprenantEnClasse(): array
{
    $formation = Formation::factory()->create(['libelle' => 'CDA Fiche']);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);
    $apprenant = Candidate::factory()->create([
        'nom' => 'Okonkwo',
        'prenom' => 'Amara',
        'formation_visee_id' => $formation->id,
        'date_naissance' => '2004-05-10',
    ]);
    $classe->apprentis()->attach($apprenant->id);

    return [$apprenant, $classe];
}

it('ouvre la fiche apprenant depuis la page Assiduité (clic sur le nom)', function () {
    [$apprenant] = apprenantEnClasse();

    Livewire::test(Assiduite::class)
        ->mountAction(TestAction::make('ficheApprenant')->table($apprenant))
        ->assertActionMounted(TestAction::make('ficheApprenant')->table($apprenant));
});

it('ouvre la fiche apprenant depuis la classe (relation manager)', function () {
    [$apprenant, $classe] = apprenantEnClasse();

    Livewire::test(ApprentisRelationManager::class, [
        'ownerRecord' => $classe,
        'pageClass' => EditPromotion::class,
    ])
        ->mountAction(TestAction::make('ficheApprenant')->table($apprenant))
        ->assertActionMounted(TestAction::make('ficheApprenant')->table($apprenant));
});

it('affiche l\'identité, la formation, la classe et les documents dans la fiche', function () {
    [$apprenant] = apprenantEnClasse();

    $document = $apprenant->documents()->create([
        'type' => DocumentType::DocumentPedagogique,
        'statut' => DocumentStatut::Recu,
        'nom_fichier' => 'bulletin-s1.pdf',
        'version' => 1,
    ]);

    $html = view('filament.apprenant-fiche', [
        'apprenant' => $apprenant->load(['formationVisee', 'promotions.formation']),
        'documents' => $apprenant->documents()->where('type', DocumentType::DocumentPedagogique)->get(),
        'assiduite' => 92,
    ])->render();

    expect($html)
        ->toContain('Amara Okonkwo')
        ->toContain('10/05/2004')
        ->toContain('CDA Fiche')
        ->toContain('CDA Fiche — 1ère année')
        ->toContain('92 %')
        ->toContain('bulletin-s1.pdf')
        ->toContain('AO'); // initiales affichées tant qu'il n'y a pas de photo
});

it('enregistre la photo et les documents pédagogiques depuis la fiche', function () {
    [$apprenant] = apprenantEnClasse();

    Livewire::test(Assiduite::class)
        ->callAction(TestAction::make('ficheApprenant')->table($apprenant), data: [
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
            'fichiers' => [UploadedFile::fake()->createWithContent('bulletin-s1.pdf', "%PDF-1.4\nbulletin\n%%EOF")],
        ])
        ->assertHasNoActionErrors();

    $apprenant->refresh();

    expect($apprenant->getFirstMedia('photo'))->not->toBeNull();

    $document = $apprenant->documents()->where('type', DocumentType::DocumentPedagogique)->first();

    expect($document)->not->toBeNull()
        ->and($document->nom_fichier)->toBe('bulletin-s1.pdf')
        ->and($document->getFirstMedia('fichier'))->not->toBeNull();
});
