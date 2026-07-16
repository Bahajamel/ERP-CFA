<?php

use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\RelationManagers\ContactsRelationManager;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('affiche l\'action « Ajouter un contact » sur la fiche entreprise (page de consultation)', function () {
    $company = Company::factory()->create();

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => ViewCompany::class,
    ])
        ->assertSuccessful()
        ->assertSee('Ajouter un contact');
});

it('affiche l\'action « Ajouter une note » sur la fiche entreprise (page de consultation)', function () {
    $company = Company::factory()->create();

    Livewire::test(NotesRelationManager::class, [
        'ownerRecord' => $company,
        'pageClass' => ViewCompany::class,
    ])
        ->assertSuccessful()
        ->assertSee('Ajouter une note');
});

it('les gestionnaires ne sont pas en lecture seule', function () {
    expect((new ContactsRelationManager)->isReadOnly())->toBeFalse()
        ->and((new NotesRelationManager)->isReadOnly())->toBeFalse();
});
