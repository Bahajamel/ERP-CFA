<?php

use App\Filament\Pages\Finance;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\FaqBots\FaqBotResource;
use App\Filament\Resources\FaqBots\Pages\EditFaqBot;
use App\Filament\Resources\Seances\SeanceResource;
use App\Livewire\AssistantIa;
use App\Models\FaqBot;
use App\Models\User;
use App\Support\Assistant\AssistantContexte;
use App\Support\Assistant\BaseFaq;
use App\Support\Assistant\RechercheFaq;
use Database\Seeders\FaqBotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Connecte un utilisateur avec le rôle voulu (aucun rôle = aucune permission). */
function assistantConnecte(?string $role = null): User
{
    $user = User::factory()->create(['is_active' => true]);

    if ($role !== null) {
        $user->syncRoles($role);
    }

    test()->actingAs($user);

    return $user;
}

// ─── Base de connaissance (source du contenu semé) ──────────────────────

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

it('ne renvoie rien pour une question hors sujet', function () {
    expect(BaseFaq::rechercher('quelle est la météo à Paris demain'))->toBeEmpty();
});

// ─── Choix de l'assistant selon la section ──────────────────────────────

it('associe chaque partie du logiciel à son assistant', function () {
    expect(AssistantContexte::moduleCourant(CandidateResource::getRouteBaseName().'.index'))->toBe('commercial')
        ->and(AssistantContexte::moduleCourant(ContractResource::getRouteBaseName().'.index'))->toBe('contrats')
        ->and(AssistantContexte::moduleCourant(Finance::getRouteName()))->toBe('finance')
        ->and(AssistantContexte::moduleCourant(SeanceResource::getRouteBaseName().'.index'))->toBe('scolarite')
        // Page hors des périmètres déclarés → assistant de repli.
        ->and(AssistantContexte::moduleCourant('filament.admin.pages.dashboard'))->toBe('pilotage');
});

it('installe les cinq assistants avec leur identité et leur contenu', function () {
    $this->seed(FaqBotSeeder::class);

    $bots = FaqBot::query()->orderBy('sort')->get();

    expect($bots->pluck('module')->all())->toBe(['commercial', 'contrats', 'finance', 'scolarite', 'pilotage'])
        // Chacun a une couleur distincte et de quoi proposer des suggestions.
        ->and($bots->pluck('color')->unique())->toHaveCount(5)
        ->and($bots->every(fn (FaqBot $b): bool => $b->entrees()->count() >= 5))->toBeTrue();
});

it('affiche l\'assistant de la section consultée, avec son accueil', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');
    // L'assistant est déduit de la page ; on le force ici pour le test.

    Livewire::test(AssistantIa::class, ['module' => 'contrats'])
        ->assertSuccessful()
        ->assertSee('Assistant Contrats & OPCO')
        ->assertSee('CERFA');
});

it('borne les réponses au périmètre de l\'assistant', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');

    $scolarite = FaqBot::query()->where('module', 'scolarite')->firstOrFail();
    $contrats = FaqBot::query()->where('module', 'contrats')->firstOrFail();

    // « CERFA » n'appartient qu'au périmètre Contrats : l'assistant Scolarité
    // ne doit rien en dire, même si l'utilisateur le lui demande.
    expect(RechercheFaq::rechercher($scolarite, 'cerfa'))->toBeEmpty()
        ->and(RechercheFaq::rechercher($contrats, 'cerfa'))->not->toBeEmpty();

    // Inversement, l'assistant Contrats ignore une question de scolarité.
    expect(RechercheFaq::rechercher($contrats, 'emploi du temps'))->toBeEmpty();
});

it('répond dans son périmètre et propose le lien vers la bonne page', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');
    // idem : assistant Commercial.

    Livewire::test(AssistantIa::class, ['module' => 'commercial'])
        ->set('question', 'comment ajouter un apprenant ?')
        ->call('envoyer')
        ->assertSet('question', '')
        ->assertSee('Ajouter un candidat'); // libellé du lien
});

it('masque le lien quand l\'utilisateur n\'a pas la permission du module', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte(); // aucun rôle

    Livewire::test(AssistantIa::class, ['module' => 'commercial'])
        ->call('demander', 'comment ajouter un apprenant ?')
        // Le lien vers la page candidats est masqué faute de permission.
        ->assertDontSee('Ajouter un candidat');
});

it('retombe sur l\'assistant de repli si la partie est interdite', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte(); // aucun accès à la finance
    // Page Finance, mais sans droit d'accès à la finance.

    Livewire::test(AssistantIa::class, ['module' => 'finance'])
        ->assertSee('Assistant Pilotage')
        ->assertDontSee('Assistant Finance');
});

it('le annonce clairement quand aucune réponse ne correspond', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');
    // idem : assistant Commercial.

    Livewire::test(AssistantIa::class, ['module' => 'commercial'])
        ->call('demander', 'quelle est la météo à Paris demain')
        ->assertSee("Je n'ai pas trouvé de réponse dans cette FAQ");
});

it('n\'affiche aucun assistant tant qu\'aucun n\'est installé', function () {
    assistantConnecte('Administrateur');

    Livewire::test(AssistantIa::class)
        ->assertSuccessful()
        ->assertDontSee('Assistant');
});

// ─── Administration de la FAQ ───────────────────────────────────────────

it('réserve la gestion des assistants à l\'administration', function () {
    assistantConnecte('Administrateur');
    expect(FaqBotResource::canAccess())->toBeTrue();

    assistantConnecte('Commercial');
    expect(FaqBotResource::canAccess())->toBeFalse();
});

it('permet de modifier l\'identité d\'un assistant sans toucher au code', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');

    $bot = FaqBot::query()->where('module', 'commercial')->firstOrFail();

    Livewire::test(EditFaqBot::class, ['record' => $bot->getKey()])
        ->fillForm([
            'name' => 'Assistant Recrutement',
            'color' => '#111827',
            'welcome_message' => 'Bonjour, en quoi puis-je aider ?',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($bot->fresh()->name)->toBe('Assistant Recrutement');

    // Le chat reprend aussitôt la nouvelle identité.
    Livewire::test(AssistantIa::class, ['module' => 'commercial'])
        ->assertSee('Assistant Recrutement')
        ->assertSee('en quoi puis-je aider');
});

it('dessine un avatar distinct pour chaque assistant', function () {
    $this->seed(FaqBotSeeder::class);
    assistantConnecte('Administrateur');

    $rendus = collect(['commercial', 'contrats', 'finance', 'scolarite', 'pilotage'])
        ->map(fn (string $module): string => Livewire::test(AssistantIa::class, ['module' => $module])->html());

    // Chaque assistant expose son avatar SVG, à sa propre couleur…
    foreach ($rendus as $html) {
        expect($html)->toContain('<svg viewBox="0 0 64 64"');
    }

    // …et les cinq dessins diffèrent (coupe, lunettes, barbe, chignon).
    expect($rendus->unique())->toHaveCount(5);
});
