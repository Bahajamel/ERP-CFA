<?php

use App\Filament\Resources\Needs\Pages\CreateNeed;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Need;
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

it('permet de rattacher un contact de l\'entreprise choisie à l\'offre', function () {
    $company = Company::factory()->create();
    $contact = CompanyContact::factory()->create([
        'company_id' => $company->id,
        'nom' => 'Durand',
        'prenom' => 'Claire',
    ]);

    Livewire::test(CreateNeed::class)
        ->fillForm([
            'company_id' => $company->id,
            'intitule_poste' => 'Alternance réseau',
            'nb_postes' => 1,
            'contact_id' => $contact->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $need = Need::query()->where('intitule_poste', 'Alternance réseau')->first();

    expect($need)->not->toBeNull()
        ->and($need->contact_id)->toBe($contact->id);
});

it('crée un contact à la volée depuis le champ « Contact responsable »', function () {
    $company = Company::factory()->create();

    expect($company->contacts()->count())->toBe(0);

    Livewire::test(CreateNeed::class)
        ->fillForm(['company_id' => $company->id])
        ->callFormComponentAction('contact_id', 'createOption', data: [
            'nom' => 'Lefevre',
            'prenom' => 'Paul',
            'fonction' => 'DRH',
            'role' => 'responsable',
            'email' => 'p.lefevre@example.test',
            'telephone' => '0612345678',
        ]);

    $contact = $company->contacts()->first();

    expect($contact)->not->toBeNull()
        ->and($contact->nom)->toBe('Lefevre')
        ->and($contact->company_id)->toBe($company->id)
        // Rôle « responsable » → marqué contact principal, pas tuteur.
        ->and((bool) $contact->is_principal)->toBeTrue()
        ->and((bool) $contact->is_tuteur)->toBeFalse();
});

it('marque le contact comme tuteur quand le rôle « tuteur » est choisi', function () {
    $company = Company::factory()->create();

    Livewire::test(CreateNeed::class)
        ->fillForm(['company_id' => $company->id])
        ->callFormComponentAction('tuteur_id', 'createOption', data: [
            'nom' => 'Nkomo',
            'prenom' => 'Awa',
            'fonction' => 'Chef d\'atelier',
            'role' => 'tuteur',
            'email' => 'a.nkomo@example.test',
            'telephone' => '0700000000',
        ]);

    $contact = $company->contacts()->first();

    expect($contact)->not->toBeNull()
        ->and((bool) $contact->is_tuteur)->toBeTrue()
        ->and((bool) $contact->is_principal)->toBeFalse();
});

it('refuse la création d\'un contact avec un e-mail invalide, un téléphone non numérique ou sans rôle', function () {
    $company = Company::factory()->create();

    // E-mail sans @ → refusé.
    Livewire::test(CreateNeed::class)
        ->fillForm(['company_id' => $company->id])
        ->callFormComponentAction('contact_id', 'createOption', data: [
            'nom' => 'Test', 'prenom' => 'A', 'fonction' => 'X',
            'role' => 'responsable', 'email' => 'pasunemail', 'telephone' => '0612345678',
        ])
        ->assertHasFormComponentActionErrors(['email']);

    // Téléphone avec des lettres → refusé.
    Livewire::test(CreateNeed::class)
        ->fillForm(['company_id' => $company->id])
        ->callFormComponentAction('contact_id', 'createOption', data: [
            'nom' => 'Test', 'prenom' => 'A', 'fonction' => 'X',
            'role' => 'responsable', 'email' => 'a@b.fr', 'telephone' => '06 ABC 12',
        ])
        ->assertHasFormComponentActionErrors(['telephone']);

    // Rôle non choisi → refusé.
    Livewire::test(CreateNeed::class)
        ->fillForm(['company_id' => $company->id])
        ->callFormComponentAction('contact_id', 'createOption', data: [
            'nom' => 'Test', 'prenom' => 'A', 'fonction' => 'X',
            'email' => 'a@b.fr', 'telephone' => '0612345678',
        ])
        ->assertHasFormComponentActionErrors(['role']);

    expect($company->contacts()->count())->toBe(0);
});

it('crée une offre même sans interlocuteur (champs facultatifs)', function () {
    $company = Company::factory()->create();

    Livewire::test(CreateNeed::class)
        ->fillForm([
            'company_id' => $company->id,
            'intitule_poste' => 'Alternance sans contact',
            'nb_postes' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Need::query()->where('intitule_poste', 'Alternance sans contact')->exists())->toBeTrue();
});
