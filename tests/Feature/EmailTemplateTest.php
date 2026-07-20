<?php

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Mail\EmailPersonnalise;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\EmailTemplate;
use App\Models\Interaction;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

it('envoie un e-mail à l\'entreprise depuis une offre et journalise l\'interaction', function () {
    Mail::fake();

    $company = Company::factory()->create(['raison_sociale' => 'Groupe Alfa']);
    CompanyContact::factory()->principal()->create([
        'company_id' => $company->id,
        'email' => 'rh@groupe-alfa.fr',
    ]);
    $need = Need::factory()->create(['company_id' => $company->id, 'intitule_poste' => 'Développeur web']);

    Livewire::test(ListNeeds::class)
        ->callTableAction('envoyerEmail', $need, data: [
            'destinataire' => 'rh@groupe-alfa.fr',
            'objet' => 'Point sur Développeur web',
            'corps' => 'Bonjour, où en est le recrutement ?',
        ]);

    Mail::assertSent(EmailPersonnalise::class, fn (EmailPersonnalise $mail): bool => $mail->hasTo('rh@groupe-alfa.fr')
        && $mail->sujet === 'Point sur Développeur web');

    // Interaction « e-mail » ajoutée à l'historique de l'entreprise.
    $interaction = Interaction::query()
        ->where('interactable_type', $company->getMorphClass())
        ->where('interactable_id', $company->id)
        ->first();

    expect($interaction)->not->toBeNull()
        ->and($interaction->type->value)->toBe('email')
        ->and($interaction->resume)->toContain('Développeur web');
});
