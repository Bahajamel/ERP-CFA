<?php

use App\Documents\FicheBesoin;
use App\Enums\DocumentSource;
use App\Enums\DocumentType;
use App\Enums\NeedOrigine;
use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Formation;
use App\Models\Need;
use App\Models\QualiopiIndicator;
use App\Models\User;
use App\Services\FicheBesoinService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Offre complète, telle qu'une entreprise l'aurait déposée. */
function offreDeposee(array $besoin = [], array $entreprise = []): Need
{
    $company = Company::factory()->create(array_merge([
        'raison_sociale' => 'Boulangerie Lecoq',
        'siret' => '90000000100011',
        'secteur' => 'Boulangerie-pâtisserie',
        'adresse' => '12 rue du Four',
    ], $entreprise));

    $contact = CompanyContact::query()->create([
        'company_id' => $company->id,
        'nom' => 'Lecoq',
        'prenom' => 'Sylvie',
        'fonction' => 'Gérante',
        'email' => 'sylvie.lecoq@example.test',
        'telephone' => '+33478000001',
        'is_principal' => true,
    ]);

    return Need::factory()->create(array_merge([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'intitule_poste' => 'Apprenti boulanger',
        'nb_postes' => 2,
        'rythme' => '1 semaine CFA / 3 semaines entreprise',
        'localisation' => '12 rue du Four, 69003 Lyon',
        'prerequis' => 'Lever tôt, goût du travail en équipe.',
        'statut' => NeedStatut::Cree,
        'origine' => NeedOrigine::Entreprise,
        'validee_at' => null,
    ], $besoin));
}

// ── Le PDF lui-même ───────────────────────────────────────────────────────────

it('produit un PDF valide', function () {
    $pdf = app(FicheBesoin::class)->pour(offreDeposee());

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(5_000);
});

it('tient sur une seule page', function () {
    $pdf = app(FicheBesoin::class)->pour(offreDeposee());

    // Une fiche besoin qui déborde est inutilisable en réunion comme en audit.
    // Le motif exclut « /Type /Pages » (le nœud racine), qui contient
    // « /Type /Page » et fausserait un simple substr_count.
    expect(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(1);
});

it('déborde proprement quand l\'entreprise écrit un texte très long', function () {
    // Cas réel : une entreprise colle une fiche de poste entière. On ne tronque
    // pas son texte — mieux vaut une 2ᵉ page qu'un besoin amputé.
    $need = offreDeposee([
        'prerequis' => str_repeat("Mission détaillée sur plusieurs lignes.\n", 120),
    ]);

    $pdf = app(FicheBesoin::class)->pour($need);

    expect(substr($pdf, 0, 5))->toBe('%PDF-')
        ->and(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBeGreaterThan(1);
});

it('numérote la fiche à partir de l\'offre', function () {
    $need = offreDeposee();
    $annee = $need->created_at->format('Y');

    expect(app(FicheBesoin::class)->reference($need))
        ->toBe('BES-'.$annee.'-'.str_pad((string) $need->id, 4, '0', STR_PAD_LEFT))
        ->and(app(FicheBesoin::class)->nomFichier($need))
        ->toStartWith('fiche-besoin-BES-')
        ->toEndWith('.pdf');
});

// ── Les données portées par la fiche ──────────────────────────────────────────

it('reprend l\'entreprise, le contact et le poste', function () {
    $d = app(FicheBesoin::class)->donnees(offreDeposee());

    expect($d['entreprise'])->toBe('Boulangerie Lecoq')
        // SIRET formaté par groupes, comme sur la convention.
        ->and($d['entreprise_siret'])->toBe('900 000 001 00011')
        ->and($d['contact_identite'])->toBe('Sylvie Lecoq (Gérante)')
        ->and($d['contact_email'])->toBe('sylvie.lecoq@example.test')
        ->and($d['poste'])->toBe('Apprenti boulanger')
        ->and($d['nb_postes'])->toBe(2)
        ->and($d['origine'])->toBe('Déposée par l\'entreprise')
        ->and($d['attend_validation'])->toBeTrue();
});

it('porte la certification visée, pivot de l\'indicateur 4', function () {
    $formation = Formation::factory()->create([
        'libelle' => 'TP Développeur Web',
        'code_rncp' => 'RNCP37674',
        'niveau' => 'Bac+2',
    ]);

    $d = app(FicheBesoin::class)->donnees(offreDeposee(['formation_id' => $formation->id]));

    expect($d['certification'])->toBe('TP Développeur Web')
        ->and($d['certification_rncp'])->toBe('RNCP37674')
        ->and($d['certification_niveau'])->toBe('Bac+2');
});

it('n\'invente rien quand l\'entreprise a laissé des champs vides', function () {
    $need = offreDeposee([
        'formation_id' => null,
        'rythme' => null,
        'date_demarrage' => null,
        'prerequis' => null,
    ]);

    $d = app(FicheBesoin::class)->donnees($need);

    expect($d['formation'])->toBeNull()
        ->and($d['rythme'])->toBeNull()
        ->and($d['date_demarrage'])->toBeNull()
        ->and($d['prerequis'])->toBeNull();

    // Et le PDF se génère quand même : c'est le cas de l'entreprise pressée.
    expect(substr(app(FicheBesoin::class)->pour($need), 0, 5))->toBe('%PDF-');
});

it('imprime des pointillés à compléter, pas des valeurs inventées', function () {
    $need = offreDeposee(['formation_id' => null, 'rythme' => null, 'prerequis' => null]);

    $html = view('pdf.fiche-besoin', ['d' => app(FicheBesoin::class)->donnees($need)])->render();

    expect($html)
        // Les données réellement saisies sont là…
        ->toContain('Boulangerie Lecoq')
        ->toContain('Apprenti boulanger')
        // …le cadre Qualiopi aussi, avec l'intitulé exact de l'indicateur…
        ->toContain('indicateur n°4')
        ->toContain('Adéquation des missions confiées à la certification visée')
        ->toContain('en amont de la contractualisation')
        // …et les champs vides deviennent des pointillés, jamais du texte inventé.
        ->toContain('class="fill"')
        ->toContain('à recueillir lors de la qualification');
});

// ── Archivage ─────────────────────────────────────────────────────────────────

it('archive la fiche sur l\'offre', function () {
    $need = offreDeposee();

    $document = app(FicheBesoinService::class)->archiver($need);

    expect($document->type)->toBe(DocumentType::FicheBesoin)
        ->and($document->source)->toBe(DocumentSource::Genere)
        ->and($document->organisation_id)->toBe($need->organisation_id)
        ->and($need->documents()->count())->toBe(1)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull();
});

it('rattache la fiche comme preuve de l\'indicateur Qualiopi n°4', function () {
    $indicateur = QualiopiIndicator::query()->create([
        'numero' => 4,
        'critere' => 2,
        'libelle' => 'Le prestataire analyse le besoin du bénéficiaire…',
        'statut' => 'a_verifier',
    ]);

    app(FicheBesoinService::class)->archiver(offreDeposee());

    $preuve = $indicateur->documents()->first();

    expect($indicateur->documents()->count())->toBe(1)
        ->and($preuve->type)->toBe(DocumentType::FicheBesoin)
        // Le fichier est bien copié, pas seulement référencé.
        ->and($preuve->getFirstMedia('fichier'))->not->toBeNull();
});

it('remplace la fiche au lieu d\'empiler les versions', function () {
    $indicateur = QualiopiIndicator::query()->create([
        'numero' => 4, 'critere' => 2, 'libelle' => 'x', 'statut' => 'a_verifier',
    ]);

    $need = offreDeposee();
    $service = app(FicheBesoinService::class);

    $premier = $service->archiver($need);
    $second = $service->archiver($need);

    expect($second->id)->toBe($premier->id)
        ->and($need->documents()->count())->toBe(1)
        ->and($indicateur->documents()->count())->toBe(1)
        ->and($second->getFirstMedia('fichier'))->not->toBeNull();
});

it('génère la fiche même sans référentiel Qualiopi initialisé', function () {
    QualiopiIndicator::query()->delete();

    $need = offreDeposee();
    $document = app(FicheBesoinService::class)->archiver($need);

    // L'absence d'indicateur ne doit pas empêcher d'imprimer une fiche.
    expect($document->getFirstMedia('fichier'))->not->toBeNull();
});

// ── Depuis l'ERP ──────────────────────────────────────────────────────────────

it('télécharge la fiche depuis la liste des offres', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $need = offreDeposee();

    Livewire::test(ListNeeds::class)
        ->callTableAction('ficheBesoin', $need)
        ->assertNotified();

    expect($need->documents()->where('type', DocumentType::FicheBesoin->value)->exists())->toBeTrue();
});
