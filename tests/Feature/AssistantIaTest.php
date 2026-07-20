<?php

use App\Livewire\AssistantIa;
use App\Models\User;
use App\Support\Assistant\BaseFaq;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ─── Base de connaissance ───────────────────────────────────────────────

it('trouve la bonne entrée pour une question d\'usage', function () {
    $resultats = BaseFaq::rechercher('comment ajouter un apprenant ?');

    expect($resultats)->not->toBeEmpty()
        ->and($resultats[0]['categorie'])->toBe('Candidats')
        ->and($resultats[0]['lien']['route'])->toBe('filament.admin.resources.candidates.create');
});

it('est insensible à la casse et aux accents', function () {
    $avecAccents = BaseFaq::rechercher('Comment générer un CERFA ?');
    $sansAccents = BaseFaq::rechercher('comment generer un cerfa');

    expect($avecAccents)->not->toBeEmpty()
        ->and($avecAccents[0]['categorie'])->toBe('Contrats')
        ->and($sansAccents[0]['question'])->toBe($avecAccents[0]['question']);
});

it('renvoie la saisie de notes pour une question sur les notes', function () {
    $resultats = BaseFaq::rechercher('où saisir les notes des élèves');

    expect($resultats[0]['lien']['route'])->toBe('filament.admin.pages.notes');
});

it('ne renvoie rien pour une question hors sujet', function () {
    expect(BaseFaq::rechercher('quelle est la météo à Paris demain'))->toBeEmpty();
});

it('connaît les tableaux personnalisés (façon Monday)', function () {
    $creation = BaseFaq::rechercher('comment créer un tableau personnalisé');
    expect($creation)->not->toBeEmpty()
        ->and($creation[0]['categorie'])->toBe('Tableaux personnalisés')
        ->and($creation[0]['lien']['route'])->toBe('filament.admin.resources.custom-tables.create');

    // Le lien public de candidature/entreprise est aussi couvert.
    $lien = BaseFaq::rechercher('partager un lien de candidature pour un tableau');
    expect($lien[0]['categorie'])->toBe('Tableaux personnalisés');
});

// ─── Composant Livewire ─────────────────────────────────────────────────

it('affiche un message d\'accueil avec des suggestions', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    Livewire::test(AssistantIa::class)
        ->assertSuccessful()
        ->assertSee('assistant du CFA')
        ->assertSee('Comment ajouter un apprenant ?');
});

it('répond à une question et propose un lien vers la bonne page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    Livewire::test(AssistantIa::class)
        ->set('question', 'comment ajouter un apprenant ?')
        ->call('envoyer')
        ->assertSet('question', '')
        ->assertSee('Candidats')
        ->assertSee('Ajouter un candidat'); // libellé du lien affiché
});

it('masque le lien quand l\'utilisateur n\'a pas la permission du module', function () {
    // Utilisateur sans rôle → aucune permission d'accès.
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user);

    Livewire::test(AssistantIa::class)
        ->call('demander', 'comment ajouter un apprenant ?')
        // La réponse texte reste visible…
        ->assertSee('Nouveau candidat')
        // …mais pas le bouton-lien vers la page (permission manquante).
        ->assertDontSee('Ajouter un candidat');
});

it('rejoue une question suggérée en un clic', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    Livewire::test(AssistantIa::class)
        ->call('demander', 'Comment établir un contrat / CERFA ?')
        ->assertSee('CERFA')
        ->assertSee('Créer un contrat');
});
