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

it('remplit le tableau « Informations générales »', function () {
    $formation = Formation::factory()->create([
        'libelle' => 'TP Développeur Web',
        'code_rncp' => 'RNCP37674',
    ]);

    $d = app(FicheBesoin::class)->donnees(offreDeposee(['formation_id' => $formation->id]));

    expect($d['entreprise'])->toBe('Boulangerie Lecoq')
        // SIRET formaté par groupes, comme sur la convention.
        ->and($d['entreprise_siret'])->toBe('900 000 001 00011')
        ->and($d['formation'])->toBe('TP Développeur Web')
        ->and($d['code_rncp'])->toBe('RNCP37674')
        ->and($d['poste'])->toBe('Apprenti boulanger')
        // Date de la fiche = mois + année en toutes lettres, capitalisés.
        ->and($d['date_fiche'])->toMatch('/^[A-ZÀ-Ý][a-zà-ÿ]+ \d{4}$/u');
});

it('rédige le contexte à partir du secteur et de l\'adresse', function () {
    $d = app(FicheBesoin::class)->donnees(offreDeposee([], [
        'secteur' => 'Boulangerie-pâtisserie',
        'adresse' => '12 rue du Four',
        'code_postal' => '69003',
        'ville' => 'Lyon',
    ]));

    expect($d['contexte'])
        ->toContain('Boulangerie Lecoq')
        ->toContain('Boulangerie-pâtisserie')
        ->toContain('12 rue du Four, 69003 Lyon');
});

it('reprend les missions écrites par l\'entreprise en § besoin opérationnel', function () {
    $d = app(FicheBesoin::class)->donnees(offreDeposee([
        'prerequis' => 'Suivi des commandes et relation client.',
    ]));

    expect($d['besoin'])->toBe('Suivi des commandes et relation client.');
});

it('liste en priorité les compétences exprimées par l\'entreprise', function () {
    // Même si la formation a un référentiel, ce que l'entreprise a écrit prime.
    $formation = Formation::factory()->create([
        'matieres' => ['Référentiel A', 'Référentiel B'],
    ]);

    $d = app(FicheBesoin::class)->donnees(offreDeposee([
        'formation_id' => $formation->id,
        'competences_attendues' => "Relation client\nOrganisation\n\nSuivi administratif",
    ]));

    // Une compétence par ligne, lignes vides ignorées.
    expect($d['competences'])->toBe(['Relation client', 'Organisation', 'Suivi administratif']);
});

it('retombe sur le référentiel de la formation quand l\'entreprise n\'a rien exprimé', function () {
    $formation = Formation::factory()->create([
        'matieres' => ['Gestion de projet', 'Relation client', 'Comptabilité'],
    ]);

    $d = app(FicheBesoin::class)->donnees(offreDeposee([
        'formation_id' => $formation->id,
        'competences_attendues' => null,
    ]));

    expect($d['competences'])->toBe(['Gestion de projet', 'Relation client', 'Comptabilité']);
});

it('génère la fiche même quand l\'entreprise a laissé des champs vides', function () {
    // L'entreprise pressée : ni formation, ni rythme, ni missions détaillées.
    $need = offreDeposee(['formation_id' => null, 'rythme' => null, 'prerequis' => null]);

    $d = app(FicheBesoin::class)->donnees($need);

    // On ne bloque pas, et on n'invente pas : le besoin est décrit sobrement…
    expect($d['formation'])->toBeNull()
        ->and($d['besoin'])->toContain('Apprenti boulanger')
        // …et les compétences renvoient au référentiel « à préciser ».
        ->and($d['competences'][0])->toContain('à préciser');

    // Le PDF se génère quand même.
    expect(substr(app(FicheBesoin::class)->pour($need), 0, 5))->toBe('%PDF-');
});

it('reste au conditionnel tant que le besoin n\'est pas validé', function () {
    $enAttente = app(FicheBesoin::class)->donnees(offreDeposee(['validee_at' => null]));
    $valide = app(FicheBesoin::class)->donnees(
        offreDeposee(['validee_at' => now()], ['siret' => '90000000900019']),
    );

    // Non validé : on n'affirme pas une adéquation que personne n'a relue.
    expect($enAttente['conclusion'])->toContain('Sous réserve')
        ->and($enAttente['conclusion'])->not->toContain('constate une cohérence');

    // Validé : le CFA constate.
    expect($valide['conclusion'])->toContain('constate une cohérence')
        // Le nom du CFA n'est jamais doublé (« le CFA CFA … »).
        ->and($valide['conclusion'])->not->toContain('CFA CFA');
});

it('n\'affirme jamais la compatibilité d\'un besoin rejeté', function () {
    // Un besoin passé en « Annulé » ne doit pas conclure « compatible » :
    // il reste au conditionnel, il n'a pas été endossé.
    $d = app(FicheBesoin::class)->donnees(offreDeposee(['statut' => NeedStatut::Annule]));

    expect($d['conclusion'])->toContain('Sous réserve')
        ->and($d['conclusion'])->not->toContain('constate une cohérence');
});

it('suit le format ACTION CBM (6 sections, sans logo, sans bas de page)', function () {
    $html = view('pdf.fiche-besoin', [
        'd' => app(FicheBesoin::class)->donnees(offreDeposee()),
    ])->render();

    expect($html)
        ->toContain('FICHE BESOIN — ALTERNANCE / APPRENTISSAGE')
        ->toContain('Document préparatoire')
        ->toContain('1. Informations générales')
        ->toContain('2. Contexte de l\'entreprise')
        ->toContain('3. Besoin opérationnel')
        ->toContain('4. Compétences attendues')
        ->toContain('5. Justification du choix de la formation')
        ->toContain('6. Conclusion')
        // Sans logo (aucune balise image) ni bloc légal de bas de page.
        ->not->toContain('<img')
        ->not->toContain('Déclaration d\'activité');
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

it('complète l\'offre depuis la fenêtre puis génère la fiche', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $formation = Formation::factory()->create();
    // Offre déposée sans formation ni compétences : le cas que la fenêtre corrige.
    $need = offreDeposee(['formation_id' => null, 'competences_attendues' => null]);

    Livewire::test(ListNeeds::class)
        ->callTableAction('ficheBesoin', $need, data: [
            'formation_id' => $formation->id,
            'competences_attendues' => "Relation client\nOrganisation",
        ])
        ->assertNotified();

    // Les compléments sont enregistrés sur l'offre, pas seulement dans le PDF.
    $need->refresh();
    expect($need->formation_id)->toBe($formation->id)
        ->and($need->competences_attendues)->toBe("Relation client\nOrganisation")
        ->and($need->documents()->where('type', DocumentType::FicheBesoin->value)->exists())->toBeTrue();
});
