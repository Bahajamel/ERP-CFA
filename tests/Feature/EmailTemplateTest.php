<?php

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Livewire\ProposerCandidatsModal;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\EmailTemplate;
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

it('remplace les variables {{clé}} (et vide les inconnues)', function () {
    $rendu = EmailTemplate::remplacer(
        'Bonjour {{contact}}, à propos de {{ offre }} chez {{entreprise}} — {{inconnue}}.',
        ['contact' => 'M. Dupont', 'offre' => 'Dév web', 'entreprise' => 'Groupe Alfa'],
    );

    expect($rendu)->toBe('Bonjour M. Dupont, à propos de Dév web chez Groupe Alfa — .');
});

it('crée un modèle d\'e-mail (auteur tracé)', function () {
    Livewire::test(CreateEmailTemplate::class)
        ->fillForm([
            'name' => 'Suivi recrutement',
            'subject' => 'Point sur {{offre}}',
            'body' => 'Bonjour {{contact}},',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $modele = EmailTemplate::query()->firstOrFail();
    expect($modele->name)->toBe('Suivi recrutement')
        ->and($modele->created_by)->toBe($this->user->id);
});

it('réserve les modèles d\'e-mail aux profils commerciaux', function () {
    expect(EmailTemplateResource::canAccess())->toBeTrue(); // Administrateur

    $formateur = User::factory()->create(['is_active' => true]);
    $formateur->syncRoles('Formateur');
    $this->actingAs($formateur);

    expect(EmailTemplateResource::canAccess())->toBeFalse();
});

it('remplit le message de « Proposer des candidats » avec le mail type choisi', function () {
    $company = Company::factory()->create(['raison_sociale' => 'Groupe Alfa']);
    CompanyContact::factory()->principal()->create([
        'company_id' => $company->id,
        'nom' => 'Dupont',
        'prenom' => 'Marc',
    ]);
    $need = Need::factory()->create(['company_id' => $company->id, 'intitule_poste' => 'Développeur web']);

    $modele = EmailTemplate::create([
        'name' => 'Suivi recrutement',
        'subject' => 'Point sur {{offre}}',
        'body' => 'Bonjour {{contact}}, au sujet de {{offre}} chez {{entreprise}}.',
    ]);

    $composant = Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        // Le sélecteur de mail type est proposé dans le modal.
        ->assertSee('Mail type')
        ->assertSee('Suivi recrutement')
        ->set('templateId', $modele->id);

    // Variables résolues depuis l'offre.
    $composant->assertSet('message', 'Bonjour Marc Dupont, au sujet de Développeur web chez Groupe Alfa.');

    // Retour à « Message par défaut » : le message initial est restauré.
    $composant->set('templateId', null)
        ->assertSet('templateId', null);
    expect($composant->get('message'))->toContain('Développeur web')
        ->and($composant->get('message'))->not->toContain('au sujet de');
});

it('ne propose que les mails types actifs dans le modal', function () {
    $need = Need::factory()->create();

    EmailTemplate::create(['name' => 'Actif', 'subject' => 'A', 'body' => 'A', 'is_active' => true]);
    EmailTemplate::create(['name' => 'Archivé', 'subject' => 'B', 'body' => 'B', 'is_active' => false]);

    $modeles = Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->instance()->modelesEmail();

    expect($modeles)->toContain('Actif')
        ->and($modeles)->not->toContain('Archivé');
});
