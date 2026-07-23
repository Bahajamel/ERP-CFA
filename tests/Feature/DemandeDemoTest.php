<?php

use App\Enums\DemoRequestStatut;
use App\Filament\Editeur\Resources\DemoRequests\DemoRequestResource;
use App\Filament\Editeur\Resources\DemoRequests\Pages\EditDemoRequest;
use App\Filament\Editeur\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Livewire\DemandeDemoForm;
use App\Mail\NouvelleDemandeDemo;
use App\Models\DemoRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('demande-demo:127.0.0.1');
});

/** Jeu de valeurs valides pour le formulaire. */
function demandeValide(array $remplacements = []): array
{
    return array_merge([
        'first_name' => 'Léa',
        'last_name' => 'Durand',
        'job_title' => 'Directrice',
        'organization_name' => 'CFA des Métiers',
        'email' => 'lea.durand@example.test',
        'phone' => '0478000001',
        'learner_count' => '50 à 200',
        'main_need' => 'Contrats et OPCO',
        'message' => 'Nous cherchons à centraliser nos dossiers.',
        'consent' => true,
    ], $remplacements);
}

/** Remplit le composant puis envoie. */
function envoyerDemande(array $valeurs = []): Testable
{
    $composant = Livewire::test(DemandeDemoForm::class);

    foreach (demandeValide($valeurs) as $champ => $valeur) {
        $composant->set($champ, $valeur);
    }

    return $composant->call('envoyer');
}

// ── Enregistrement ────────────────────────────────────────────────────────────

it('enregistre une demande valide et affiche la confirmation', function () {
    envoyerDemande()
        ->assertHasNoErrors()
        ->assertSet('envoye', true)
        ->assertSee('Merci pour votre demande');

    $demande = DemoRequest::firstOrFail();

    expect($demande->first_name)->toBe('Léa')
        ->and($demande->organization_name)->toBe('CFA des Métiers')
        ->and($demande->email)->toBe('lea.durand@example.test')
        ->and($demande->learner_count)->toBe('50 à 200')
        ->and($demande->status)->toBe(DemoRequestStatut::Nouveau)
        // Le consentement est horodaté (traçabilité RGPD).
        ->and($demande->consent_at)->not->toBeNull();
});

it('refuse une demande incomplète', function () {
    Livewire::test(DemandeDemoForm::class)
        ->call('envoyer')
        ->assertHasErrors(['first_name', 'last_name', 'organization_name', 'email', 'consent']);

    expect(DemoRequest::count())->toBe(0);
});

it('exige le consentement', function () {
    envoyerDemande(['consent' => false])->assertHasErrors('consent');

    expect(DemoRequest::count())->toBe(0);
});

it('refuse une adresse e-mail invalide', function () {
    envoyerDemande(['email' => 'pas-une-adresse'])->assertHasErrors('email');

    expect(DemoRequest::count())->toBe(0);
});

it('refuse des caractères non humains dans le nom', function () {
    envoyerDemande(['first_name' => '<script>alert(1)</script>'])->assertHasErrors('first_name');

    expect(DemoRequest::count())->toBe(0);
});

it('nettoie les balises HTML des champs libres', function () {
    envoyerDemande([
        'organization_name' => 'CFA <b>des Métiers</b>',
        'message' => 'Bonjour <script>alert(1)</script>',
    ])->assertHasNoErrors();

    $demande = DemoRequest::firstOrFail();

    expect($demande->organization_name)->toBe('CFA des Métiers')
        ->and($demande->message)->toBe('Bonjour alert(1)');
});

// ── Anti-spam ─────────────────────────────────────────────────────────────────

it('ignore silencieusement une soumission piégée par le pot de miel', function () {
    envoyerDemande(['website' => 'https://spam.test'])
        ->assertSet('envoye', true);

    // Le robot voit la confirmation, mais rien n'est enregistré.
    expect(DemoRequest::count())->toBe(0);
});

it('limite le nombre de demandes par adresse IP', function () {
    // Cinq envois passent, le sixième est refusé.
    for ($i = 1; $i <= 5; $i++) {
        envoyerDemande(['email' => "prospect{$i}@example.test"])->assertHasNoErrors();
    }

    envoyerDemande(['email' => 'prospect6@example.test'])->assertHasErrors('email');

    expect(DemoRequest::count())->toBe(5);
});

// ── Notification de l'équipe ──────────────────────────────────────────────────

it('prévient l\'équipe éditeur par la cloche et par e-mail', function () {
    $this->seed(RolePermissionSeeder::class);

    $editeur = User::factory()->create(['is_active' => true]);
    $editeur->syncRoles('Éditeur');

    // Un utilisateur sans le droit éditeur ne doit pas être notifié.
    $autre = User::factory()->create(['is_active' => true]);

    envoyerDemande()->assertHasNoErrors();

    expect($editeur->notifications()->count())->toBe(1)
        ->and($autre->notifications()->count())->toBe(0);

    Mail::assertSent(NouvelleDemandeDemo::class, fn ($mail): bool => $mail->hasTo($editeur->email));
});

it('enregistre la demande même sans équipe éditeur configurée', function () {
    envoyerDemande()->assertHasNoErrors();

    expect(DemoRequest::count())->toBe(1);
    Mail::assertNothingSent();
});

// ── Vitrine ───────────────────────────────────────────────────────────────────

it('affiche le formulaire câblé sur la page vitrine', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Demander une démonstration')
        // Livewire est bien monté (le formulaire n'est plus une maquette).
        ->assertSee('wire:submit', false);
});

// ── Espace éditeur ────────────────────────────────────────────────────────────

describe('espace éditeur', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('editeur'));

        $this->editeur = User::factory()->create(['is_active' => true]);
        $this->editeur->syncRoles('Éditeur');
        $this->actingAs($this->editeur);
    });

    it('liste les demandes reçues', function () {
        $demande = DemoRequest::create(demandeEnBase());

        Livewire::test(ListDemoRequests::class)
            ->assertCanSeeTableRecords([$demande])
            ->assertSee('CFA des Métiers');
    });

    it('permet de qualifier une demande sans toucher aux données du prospect', function () {
        $demande = DemoRequest::create(demandeEnBase());

        Livewire::test(EditDemoRequest::class, ['record' => $demande->getKey()])
            ->fillForm([
                'status' => DemoRequestStatut::DemoPlanifiee->value,
                'notes_internes' => 'Démonstration calée le 12/09.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $demande->refresh();

        expect($demande->status)->toBe(DemoRequestStatut::DemoPlanifiee)
            ->and($demande->notes_internes)->toBe('Démonstration calée le 12/09.')
            // Les informations du prospect restent celles qu'il a transmises.
            ->and($demande->email)->toBe('lea.durand@example.test');
    });

    it('compte les demandes à traiter dans la navigation', function () {
        DemoRequest::create(demandeEnBase());
        DemoRequest::create(demandeEnBase(['email' => 'x@example.test', 'status' => DemoRequestStatut::Converti]));

        // Seules les demandes encore ouvertes sont signalées.
        expect(DemoRequestResource::getNavigationBadge())->toBe('1');
    });

    it('n\'autorise pas la création manuelle d\'une demande', function () {
        expect(DemoRequestResource::canCreate())->toBeFalse();
    });
});

/** Attributs d'une demande déjà enregistrée (sans passer par le formulaire). */
function demandeEnBase(array $remplacements = []): array
{
    return array_merge([
        'first_name' => 'Léa',
        'last_name' => 'Durand',
        'organization_name' => 'CFA des Métiers',
        'email' => 'lea.durand@example.test',
        'status' => DemoRequestStatut::Nouveau,
        'consent_at' => now(),
    ], $remplacements);
}
