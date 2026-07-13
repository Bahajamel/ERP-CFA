<?php

use App\Enums\AdmissionStatut;
use App\Mail\InvitationInscription;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\CandidatePromotion;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use App\Scolarite\InscriptionService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->syncRoles('Administrateur');
    $this->actingAs($this->admin);
});

/** Formation + cohorte + candidat de cette formation. */
function contexteInscription(array $matieres = ['Développement web', 'Anglais', 'Cybersécurité']): array
{
    $formation = Formation::factory()->create(['matieres' => $matieres]);
    $candidate = Candidate::factory()->create(['formation_visee_id' => $formation->id, 'email' => 'apprenti@example.test']);
    $promotion = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année']);

    return [$formation, $candidate, $promotion];
}

// ─── Service ────────────────────────────────────────────────────────────

it('rattache l\'apprenant à la cohorte et génère une invitation tokenisée', function () {
    [, $candidate, $promotion] = contexteInscription();

    $pivot = app(InscriptionService::class)->inviter($candidate, $promotion);

    expect($candidate->promotions()->whereKey($promotion->id)->exists())->toBeTrue()
        ->and($pivot->invitation_token)->not->toBeNull()
        ->and($pivot->invited_at)->not->toBeNull()
        ->and($pivot->responded_at)->toBeNull();
});

it('enregistre les matières choisies, bornées au programme de la formation', function () {
    [, $candidate, $promotion] = contexteInscription(['Développement web', 'Anglais']);
    $service = app(InscriptionService::class);
    $pivot = $service->inviter($candidate, $promotion);

    $service->enregistrerChoix($pivot, ['Développement web', 'Matière pirate']);

    expect($pivot->fresh()->matieres)->toBe(['Développement web']) // « Matière pirate » rejetée
        ->and($pivot->fresh()->responded_at)->not->toBeNull();
});

// ─── Action d'admission ─────────────────────────────────────────────────

it('affecte à une classe et envoie l\'invitation depuis une admission validée', function () {
    Mail::fake();
    [$formation, $candidate, $promotion] = contexteInscription();

    // Admission valide adossée à ce candidat.
    $admission = Admission::factory()->create(['candidate_id' => $candidate->id]);
    $admission->candidate->update(['formation_visee_id' => $formation->id, 'email' => 'apprenti@example.test']);
    $admission->forceFill(['statut' => AdmissionStatut::Valide->value])->save();

    Livewire::test(\App\Filament\Resources\Admissions\Pages\ListAdmissions::class)
        ->callAction(
            \Filament\Actions\Testing\TestAction::make('affecterClasse')->table($admission),
            data: ['promotion_id' => $promotion->id],
        )
        ->assertHasNoActionErrors();

    Mail::assertSent(InvitationInscription::class);
    expect(CandidatePromotion::query()
        ->where('candidate_id', $admission->candidate_id)
        ->where('promotion_id', $promotion->id)
        ->whereNotNull('invitation_token')
        ->exists())->toBeTrue();
});

// ─── Formulaire public ──────────────────────────────────────────────────

it('affiche le formulaire de choix des matières pour un jeton valide', function () {
    [$formation, $candidate, $promotion] = contexteInscription(['Développement web', 'Anglais']);
    $pivot = app(InscriptionService::class)->inviter($candidate, $promotion);

    $this->get(route('inscription.matieres', $pivot->invitation_token))
        ->assertOk()
        ->assertSee('Développement web')
        ->assertSee('Anglais')
        ->assertSee('Valider mon inscription');
});

it('refuse un jeton inconnu ou expiré', function () {
    // Jeton inconnu.
    $this->get(route('inscription.matieres', 'jeton-bidon'))
        ->assertOk()
        ->assertSee('Ce lien n\'est plus valable');

    // Jeton expiré.
    [, $candidate, $promotion] = contexteInscription();
    $pivot = app(InscriptionService::class)->inviter($candidate, $promotion);
    $pivot->forceFill(['invited_at' => now()->subDays(InscriptionService::EXPIRATION_JOURS + 1)])->save();

    $this->get(route('inscription.matieres', $pivot->invitation_token))
        ->assertSee('Ce lien n\'est plus valable');
});

it('enregistre le choix, inscrit l\'apprenant et redirige vers le merci', function () {
    [, $candidate, $promotion] = contexteInscription(['Développement web', 'Anglais', 'Cybersécurité']);
    $pivot = app(InscriptionService::class)->inviter($candidate, $promotion);

    $this->post(route('inscription.matieres.store', $pivot->invitation_token), [
        'matieres' => ['Développement web', 'Cybersécurité'],
    ])->assertRedirect(route('inscription.merci'));

    $pivot->refresh();
    expect($pivot->matieres)->toBe(['Développement web', 'Cybersécurité'])
        ->and($pivot->responded_at)->not->toBeNull()
        ->and($candidate->promotions()->whereKey($promotion->id)->exists())->toBeTrue();
});

it('rejette une matière hors programme', function () {
    [, $candidate, $promotion] = contexteInscription(['Développement web']);
    $pivot = app(InscriptionService::class)->inviter($candidate, $promotion);

    $this->post(route('inscription.matieres.store', $pivot->invitation_token), [
        'matieres' => ['Matière pirate'],
    ])->assertSessionHasErrors('matieres.0');

    expect($pivot->fresh()->responded_at)->toBeNull();
});

it('affiche l\'état « déjà répondu » après enregistrement', function () {
    [, $candidate, $promotion] = contexteInscription(['Développement web']);
    $service = app(InscriptionService::class);
    $pivot = $service->inviter($candidate, $promotion);
    $service->enregistrerChoix($pivot, ['Développement web']);

    $this->get(route('inscription.matieres', $pivot->invitation_token))
        ->assertOk()
        ->assertSee('déjà choisi vos matières');
});
