<?php

use App\Enums\DocumentType;
use App\Enums\InvoiceStatut;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Mail\AccesEspaceEntreprise;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\FinanceLine;
use App\Models\Formation;
use App\Models\Invoice;
use App\Models\User;
use App\Portail\PortailEntrepriseService;
use Database\Factories\CandidateFactory;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Une entreprise avec un contact (e-mail) et un alternant (contrat + candidat).
 *
 * @return array{0: Company, 1: Candidate, 2: Contract}
 */
function entrepriseAvecAlternant(array $companyAttrs = [], bool $avecContact = true): array
{
    $company = Company::factory()->create($companyAttrs);

    if ($avecContact) {
        CompanyContact::factory()->create([
            'company_id' => $company->id,
            'is_principal' => true,
            'email' => 'rh@'.Str::random(6).'.test',
        ]);
    }

    $formation = Formation::factory()->create(['libelle' => 'CDA']);
    $candidate = Candidate::factory()->create(['formation_visee_id' => $formation->id]);
    $contract = Contract::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'formation_id' => $formation->id,
    ]);

    return [$company, $candidate, $contract];
}

// ── Service ───────────────────────────────────────────────────────────────────

it('génère un jeton entreprise stable et un lien vers l\'espace', function () {
    [$company] = entrepriseAvecAlternant();
    $service = app(PortailEntrepriseService::class);

    $t1 = $service->jeton($company);
    $t2 = $service->jeton($company->fresh());

    expect($t1)->toBe($t2)
        ->and(strlen($t1))->toBe(48)
        ->and($service->lienPour($company->fresh()))->toContain($t1);
});

it('régénère le jeton entreprise et invalide l\'ancien lien', function () {
    [$company] = entrepriseAvecAlternant();
    $service = app(PortailEntrepriseService::class);

    $ancien = $service->jeton($company);
    $nouveau = $service->regenerer($company->fresh());

    expect($nouveau)->not->toBe($ancien)
        ->and($service->parToken($ancien))->toBeNull()
        ->and($service->parToken($nouveau)?->id)->toBe($company->id);
});

// ── Accès à l'espace ──────────────────────────────────────────────────────────

it('ouvre l\'accueil de l\'entreprise et liste ses alternants', function () {
    [$company, $candidate] = entrepriseAvecAlternant();
    $token = app(PortailEntrepriseService::class)->jeton($company);

    $this->get(route('portail.entreprise', ['token' => $token]))
        ->assertOk()
        ->assertSee($company->raison_sociale)
        ->assertSee($candidate->nom_complet)
        ->assertSee('Assiduité globale', false);
});

it('rejette un lien d\'espace entreprise inconnu (404)', function () {
    $this->get(route('portail.entreprise', ['token' => 'jeton-inexistant']))
        ->assertNotFound();
});

it('affiche le détail des alternants', function () {
    [$company, $candidate] = entrepriseAvecAlternant();
    $token = app(PortailEntrepriseService::class)->jeton($company);

    $this->get(route('portail.entreprise.alternants', ['token' => $token]))
        ->assertOk()
        ->assertSee('Mes alternants')
        ->assertSee($candidate->nom_complet);
});

// ── Documents ─────────────────────────────────────────────────────────────────

it('liste et télécharge une convention rattachée à un contrat de l\'entreprise', function () {
    [$company, , $contract] = entrepriseAvecAlternant();
    $doc = $contract->documents()->create(['type' => DocumentType::Convention->value, 'nom_fichier' => 'Convention']);
    $doc->addMediaFromString(CandidateFactory::pdfDemo())->usingFileName('conv.pdf')->toMediaCollection('fichier');
    $token = app(PortailEntrepriseService::class)->jeton($company);

    $this->get(route('portail.entreprise.documents', ['token' => $token]))
        ->assertOk()
        ->assertSee('Convention');

    $this->get(route('portail.entreprise.document', ['token' => $token, 'document' => $doc->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('interdit l\'accès aux documents d\'une autre entreprise', function () {
    [$moi] = entrepriseAvecAlternant();
    [, , $contratAutre] = entrepriseAvecAlternant();
    $docAutre = $contratAutre->documents()->create(['type' => DocumentType::Convention->value, 'nom_fichier' => 'Convention']);
    $docAutre->addMediaFromString(CandidateFactory::pdfDemo())->usingFileName('c.pdf')->toMediaCollection('fichier');
    $token = app(PortailEntrepriseService::class)->jeton($moi);

    $this->get(route('portail.entreprise.document', ['token' => $token, 'document' => $docAutre->id]))
        ->assertNotFound();
});

// ── Factures ──────────────────────────────────────────────────────────────────

it('affiche les factures de l\'entreprise et pas celles des autres', function () {
    [$company, $candidate, $contract] = entrepriseAvecAlternant();
    $ligne = FinanceLine::factory()->create(['contract_id' => $contract->id]);
    Invoice::factory()->create(['finance_line_id' => $ligne->id, 'statut' => InvoiceStatut::Emise, 'montant' => 4000]);

    // Une autre entreprise avec sa propre facture : ne doit pas apparaître.
    [, $autreCandidat, $autreContrat] = entrepriseAvecAlternant();
    $autreLigne = FinanceLine::factory()->create(['contract_id' => $autreContrat->id]);
    Invoice::factory()->create(['finance_line_id' => $autreLigne->id, 'statut' => InvoiceStatut::Emise]);

    $token = app(PortailEntrepriseService::class)->jeton($company);

    $this->get(route('portail.entreprise.factures', ['token' => $token]))
        ->assertOk()
        ->assertSee('Factures')
        ->assertSee($candidate->nom_complet)
        ->assertDontSee($autreCandidat->nom_complet);
});

// ── White-label ───────────────────────────────────────────────────────────────

it('teinte le portail entreprise aux couleurs du CFA', function () {
    Filament::getTenant()->update(['couleur_primaire' => '#10b981']);
    [$company] = entrepriseAvecAlternant();
    $token = app(PortailEntrepriseService::class)->jeton($company);

    $this->get(route('portail.entreprise', ['token' => $token]))
        ->assertOk()
        ->assertSee('--primary-600:'.Color::hex('#10b981')[600], false);
});

// ── E-mail d'accès (unitaire + groupé) ────────────────────────────────────────

it('compose l\'e-mail d\'accès entreprise avec le lien', function () {
    [$company] = entrepriseAvecAlternant(['raison_sociale' => 'ACME SARL']);
    $lien = app(PortailEntrepriseService::class)->lienPour($company);

    $rendu = (new AccesEspaceEntreprise($company, $lien, 'Mon CFA'))->render();

    expect($rendu)->toContain('ACME SARL')
        ->and($rendu)->toContain($lien);
});

it('envoie l\'accès entreprise en lot et ignore celles sans contact e-mail', function () {
    Mail::fake();
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    [$avecContact] = entrepriseAvecAlternant();
    [$sansContact] = entrepriseAvecAlternant(avecContact: false);

    Livewire::test(ListCompanies::class)
        ->callTableBulkAction('envoyerEspaceEntreprise', [$avecContact->getKey(), $sansContact->getKey()]);

    Mail::assertSent(AccesEspaceEntreprise::class, 1);
});
