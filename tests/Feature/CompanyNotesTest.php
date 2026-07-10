<?php

use App\Enums\NoteType;
use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\Note;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('ne compte comme incidents que les notes de type incident', function () {
    $company = Company::factory()->create();

    Note::factory()->for($company, 'notable')->incident()->count(2)->create();
    Note::factory()->for($company, 'notable')->create(); // note simple
    Note::factory()->for($company, 'notable')->satisfaction(5)->create();

    expect($company->incidents()->count())->toBe(2);
});

it('renvoie la satisfaction la plus récente', function () {
    $company = Company::factory()->create();

    Note::factory()->for($company, 'notable')->satisfaction(2)->create();
    Note::factory()->for($company, 'notable')->satisfaction(5)->create(); // la plus récente

    expect($company->derniereSatisfaction())->toBe(5);
});

it('renvoie null quand aucune satisfaction n\'a été saisie', function () {
    $company = Company::factory()->create();
    Note::factory()->for($company, 'notable')->incident()->create();

    expect($company->derniereSatisfaction())->toBeNull();
});

it('crée une note via le gestionnaire et renseigne automatiquement l\'auteur', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $company = Company::factory()->create();

    Livewire::test(NotesRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => EditCompany::class,
    ])
        ->callTableAction('create', data: [
            'type' => NoteType::Incident->value,
            'contenu' => 'Retard de signature du CERFA côté entreprise.',
        ]);

    $note = $company->notes()->firstOrFail();

    expect($note->type)->toBe(NoteType::Incident)
        ->and($note->contenu)->toContain('CERFA')
        ->and($note->author_id)->toBe($user->id);
});

it('enregistre le niveau de satisfaction saisi', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $company = Company::factory()->create();

    Livewire::test(NotesRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => EditCompany::class,
    ])
        ->callTableAction('create', data: [
            'type' => NoteType::Satisfaction->value,
            'satisfaction' => 5,
            'contenu' => 'Entreprise très satisfaite du suivi.',
        ]);

    expect($company->derniereSatisfaction())->toBe(5);
});
