<?php

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\User;
use App\Services\DossierCompletion;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Fiche « tour de contrôle » du dossier contrat (phase 2) : onglets Suivi /
 * Étudiant / Entreprise / Contrat / Gestion / Calendrier. Les onglets Étudiant
 * et Entreprise éditent les modèles liés (candidat, entreprise, contacts,
 * tuteur), préremplis à l'ouverture et sauvegardés proprement.
 */
function adminDossier(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

function dossierComplet(): Contract
{
    $cand = Candidate::factory()->create(['nom' => 'Saadi', 'prenom' => 'Raslen', 'email' => 'raslen@example.com']);
    $co = Company::factory()->create(['raison_sociale' => 'BNP PARIBAS']);

    return Contract::factory()->create([
        'candidate_id' => $cand->id,
        'company_id' => $co->id,
        'tuteur_id' => null,
        'statut_contrat' => ContractStatut::EnCours,
        'salaire_mensuel_brut' => null,
    ]);
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminDossier());
});

it('ouvre la fiche dossier à onglets et préremplit les champs des modèles liés', function () {
    $contract = dossierComplet();
    $co = $contract->company;
    CompanyContact::factory()->create(['company_id' => $co->id, 'is_principal' => true, 'nom' => 'Dupont', 'email' => 'contact@example.com']);
    $tuteur = CompanyContact::factory()->create(['company_id' => $co->id, 'is_tuteur' => true, 'nom' => 'Martin', 'fonction' => 'Chef d\'atelier']);
    $contract->update(['tuteur_id' => $tuteur->id]);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'etudiant_nom' => 'Saadi',
            'etudiant_email' => 'raslen@example.com',
            'entreprise_raison_sociale' => 'BNP PARIBAS',
            'contact_nom' => 'Dupont',
            'tuteur_nom' => 'Martin',
            'tuteur_fonction' => 'Chef d\'atelier',
        ]);
});

it('sauvegarde l\'onglet Étudiant sur le candidat lié', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'etudiant_nom' => 'Nouveau',
            'etudiant_lieu_naissance' => 'Lyon',
            'etudiant_sexe' => 'M',
            'etudiant_nationalite' => 'francaise',
            'etudiant_situation_avant_contrat' => 'etudiant',
            'etudiant_rqth' => true,
            'etudiant_niveau_diplome_max' => 'bac',
            'etudiant_num_secu' => '1901292000000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cand = $contract->candidate->refresh();
    expect($cand->nom)->toBe('Nouveau')
        ->and($cand->lieu_naissance)->toBe('Lyon')
        ->and($cand->sexe)->toBe('M')
        ->and($cand->nationalite)->toBe('francaise')
        ->and($cand->situation_avant_contrat)->toBe('etudiant')
        ->and($cand->rqth)->toBeTrue()
        ->and($cand->niveau_diplome_max)->toBe('bac')
        // NIR chiffré au repos : illisible en base, déchiffré à la lecture.
        ->and($cand->num_secu)->toBe('1901292000000')
        ->and($cand->getRawOriginal('num_secu'))->not->toBe('1901292000000');
});

it('sauvegarde l\'onglet Entreprise : société, contact et représentant légal', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'entreprise_forme_juridique' => 'SAS',
            'entreprise_ville_rcs' => 'Paris',
            'contact_prenom' => 'Paul',
            'contact_nom' => 'Durand',
            'contact_email' => 'paul@example.com',
            'representant_prenom' => 'Sophie',
            'representant_nom' => 'Bidule',
            'representant_email' => 'sophie@example.com',
            'representant_poste' => 'Gérante',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $co = $contract->company->refresh();
    expect($co->forme_juridique)->toBe('SAS')
        ->and($co->ville_rcs)->toBe('Paris')
        ->and($co->contactPrincipal()->where('email', 'paul@example.com')->exists())->toBeTrue()
        ->and($co->representantsLegaux()->where('email', 'sophie@example.com')->where('fonction', 'Gérante')->exists())->toBeTrue();
});

it('crée le maître d\'apprentissage quand aucun n\'est désigné', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'tuteur_prenom' => 'Marc',
            'tuteur_nom' => 'Petit',
            'tuteur_email' => 'marc@example.com',
            'tuteur_fonction' => 'Responsable technique',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->tuteur)->not->toBeNull()
        ->and($contract->tuteur->is_tuteur)->toBeTrue()
        ->and($contract->tuteur->email)->toBe('marc@example.com')
        ->and($contract->tuteur->company_id)->toBe($contract->company_id);
});

it('sauvegarde les champs du contrat (emploi, durée hebdo, date de signature)', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'emploi_occupe' => 'Développeur web',
            'duree_hebdo_heures' => 35,
            'date_signature' => '2026-07-20',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->emploi_occupe)->toBe('Développeur web')
        ->and($contract->duree_hebdo_heures)->toBe(35)
        ->and($contract->date_signature?->format('Y-m-d'))->toBe('2026-07-20');
});

it('sauvegarde l\'onglet Entreprise : infos société, RAF, contact facturation et facturation', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'entreprise_caisse_retraite' => 'AGIRC-ARRCO',
            'entreprise_nombre_salaries' => 42,
            'entreprise_code_ape_naf' => '6201Z',
            'entreprise_secteur' => 'public',
            'entreprise_type_employeur' => 'entreprise_rcs',
            'entreprise_type_employeur_specifique' => 'aucun',
            'lieu_execution' => '10 rue du Travail',
            'lieu_execution_code_postal' => '75001',
            'lieu_execution_ville' => 'Paris',
            'facturation_entite_nom' => 'BNP Facturation',
            'numero_accord_prealable' => 'ACC-2026-01',
            'raf_prenom' => 'Alice',
            'raf_nom' => 'Comptable',
            'raf_email' => 'alice@bnp.fr',
            'fac_prenom' => 'Bob',
            'fac_nom' => 'Factu',
            'fac_email' => 'bob@bnp.fr',
            'modalite_envoi_facture' => 'email',
            'facturation_emails' => [['email' => 'dest1@bnp.fr'], ['email' => 'dest2@bnp.fr']],
            'informations_annexes' => 'aucune',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $co = $contract->company->refresh();
    $contract->refresh();

    expect($co->caisse_retraite)->toBe('AGIRC-ARRCO')
        ->and($co->nombre_salaries)->toBe(42)
        ->and($co->code_ape_naf)->toBe('6201Z')
        ->and($co->secteur_type)->toBe('public')
        ->and($co->type_employeur)->toBe('entreprise_rcs')
        ->and($co->type_employeur_specifique)->toBe('aucun')
        ->and($contract->lieu_execution)->toBe('10 rue du Travail')
        // Champs réservés au secteur public : visibles et enregistrés.
        ->and($contract->numero_accord_prealable)->toBe('ACC-2026-01')
        ->and($contract->facturation_entite_nom)->toBe('BNP Facturation')
        ->and($contract->facturation_emails)->toBe([['email' => 'dest1@bnp.fr'], ['email' => 'dest2@bnp.fr']])
        // RAF et contact facturation créés avec leurs rôles.
        ->and($co->responsablesFinanciers()->where('email', 'alice@bnp.fr')->exists())->toBeTrue()
        ->and($co->contactsFacturation()->where('email', 'bob@bnp.fr')->exists())->toBeTrue();
});

it('crée un second maître d\'apprentissage quand « Oui » est sélectionné', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'tuteur_prenom' => 'Marc', 'tuteur_nom' => 'Petit', 'tuteur_email' => 'marc@ex.com',
            'second_maitre' => 1,
            'tuteur2_prenom' => 'Julie', 'tuteur2_nom' => 'Grand', 'tuteur2_email' => 'julie@ex.com',
            'tuteur2_fonction' => 'Cheffe de projet',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->tuteur?->email)->toBe('marc@ex.com')
        ->and($contract->tuteur2)->not->toBeNull()
        ->and($contract->tuteur2->email)->toBe('julie@ex.com')
        ->and($contract->tuteur2->is_tuteur)->toBeTrue()
        ->and($contract->second_maitre)->toBeTrue();
});

it('ne crée pas de second maître si « Non »', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'second_maitre' => 0,
            'tuteur2_prenom' => 'Ignoré', 'tuteur2_nom' => 'Ignoré',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->refresh()->tuteur2_id)->toBeNull();
});

it('calcule un score de complétude par onglet', function () {
    $contract = dossierComplet();

    $result = app(DossierCompletion::class)->pour($contract);

    expect($result)->toHaveKeys(['global', 'sections'])
        ->and(collect($result['sections'])->pluck('key')->all())
        ->toBe(['etudiant', 'entreprise', 'contrat', 'gestion'])
        ->and($result['global'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);

    // L'onglet Étudiant est partiellement rempli (nom, prénom, email présents).
    $etudiant = collect($result['sections'])->firstWhere('key', 'etudiant');
    expect($etudiant['score'])->toBeGreaterThan(0);
});

it('sauvegarde l\'onglet Contrat : termes, dates, avantages et missions', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'type_contrat' => 'apprentissage',
            'nature_contrat' => 'premier_contrat',
            'derogation' => 0,
            'code_rncp' => 'RNCP34556',
            'duree_hebdo_heures' => 35,
            'duree_hebdo_minutes' => 30,
            'avantage_repas' => 4.50,
            'avantage_logement' => 120,
            'autres_avantages' => 1,
            'autres_avantages_detail' => 'Véhicule de service',
            'emploi_occupe' => 'Développeur web',
            'travail_dangereux' => 0,
            'missions' => 'Développement d\'applications web.',
            'date_debut_contrat' => '2026-09-01',
            'date_fin_contrat' => '2028-08-31',
            'date_fin_periode_essai' => '2026-10-15',
            'date_conclusion' => '2026-08-20',
            'date_debut_formation_pratique' => '2026-09-01',
            'smc' => 0,
            // Au-dessus du plancher légal quel que soit l'âge (100 % SMIC max).
            'salaire_mensuel_brut' => 2000,
            'pourcentage_smic' => 55,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->nature_contrat)->toBe('premier_contrat')
        ->and((bool) $contract->derogation)->toBeFalse()
        ->and($contract->duree_hebdo_heures)->toBe(35)
        ->and($contract->duree_hebdo_minutes)->toBe(30)
        ->and((float) $contract->avantage_repas)->toBe(4.5)
        ->and((bool) $contract->autres_avantages)->toBeTrue()
        ->and($contract->autres_avantages_detail)->toBe('Véhicule de service')
        ->and($contract->missions)->toBe('Développement d\'applications web.')
        ->and($contract->date_debut_contrat?->format('Y-m-d'))->toBe('2026-09-01')
        ->and($contract->date_fin_contrat?->format('Y-m-d'))->toBe('2028-08-31')
        ->and($contract->date_conclusion?->format('Y-m-d'))->toBe('2026-08-20')
        ->and((float) $contract->pourcentage_smic)->toBe(55.0);
});

it('sauvegarde l\'onglet Contrat : finances, reste à charge, calendriers et frais', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'cout_formation' => 8000,
            'npec_annuel' => 7500,
            'npec_journalier' => 20.55,
            'nombre_jours_contrat' => 365,
            'engagement_opco_total' => 7500,
            'reste_a_charge_zero' => 0,
            'reste_a_charge_montant' => 500,
            'participation_obligatoire' => 200,
            'participation_cfa' => 100,
            'net_a_payer' => 600,
            'remuneration_annuelle' => [
                ['annee' => 1, 'base' => 'smic', 'date_debut' => '2026-09-01', 'date_fin' => '2027-08-31', 'pourcentage' => 55],
            ],
            'calendrier_financement' => [
                ['annee' => 1, 'formation' => 4000, 'financement' => 3800, 'geste_commercial' => 0, 'reste_a_charge' => 200],
                ['annee' => 2, 'formation' => 4000, 'financement' => 3700, 'geste_commercial' => 0, 'reste_a_charge' => 300],
                ['annee' => 3, 'formation' => 0, 'financement' => 0, 'geste_commercial' => 0, 'reste_a_charge' => 0],
            ],
            'frais_hebergement' => 1,
            'frais_restauration' => 0,
            'frais_equipement' => 1,
            'frais_mobilite' => 0,
            'type_equipement' => 'lien_formation',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect((float) $contract->cout_formation)->toBe(8000.0)
        ->and((float) $contract->npec_annuel)->toBe(7500.0)
        ->and($contract->nombre_jours_contrat)->toBe(365)
        ->and((float) $contract->net_a_payer)->toBe(600.0)
        ->and($contract->remuneration_annuelle)->toHaveCount(1)
        ->and($contract->calendrier_financement)->toHaveCount(3)
        ->and((bool) $contract->frais_hebergement)->toBeTrue()
        ->and((bool) $contract->frais_restauration)->toBeFalse()
        ->and((bool) $contract->frais_equipement)->toBeTrue()
        ->and($contract->type_equipement)->toBe('lien_formation');
});

it('onglet Gestion : sauvegarde les notes et les réglages du dossier', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'notes_internes' => 'Relancer l\'employeur pour le SIRET.',
            'relances_activees' => false,
            'facturation_opco' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->notes_internes)->toBe('Relancer l\'employeur pour le SIRET.')
        ->and($contract->relances_activees)->toBeFalse()
        ->and($contract->facturation_opco)->toBeFalse();
});

it('onglet Gestion : le statut de relecture reflète l\'état du dossier', function () {
    $contract = dossierComplet();

    // Dossier neuf, sans document : « En cours ».
    expect($contract->relectureStatut()['label'])->toBe('En cours')
        ->and($contract->estNonConforme())->toBeFalse()
        ->and($contract->estAnnule())->toBeFalse();

    $contract->forceFill(['non_conforme' => true])->save();
    expect($contract->refresh()->relectureStatut()['label'])->toBe('Non conforme');

    $contract->forceFill(['annule_at' => now()])->save();
    expect($contract->refresh()->relectureStatut()['label'])->toBe('Annulé')
        ->and($contract->estAnnule())->toBeTrue();
});

it('onglet Gestion : engage le dossier (En cours → Manque la signature)', function () {
    $contract = dossierComplet();
    expect($contract->statut_contrat)->toBe(ContractStatut::EnCours);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('engagerDossier')->schemaComponent())
        ->assertHasNoActionErrors();

    expect($contract->refresh()->statut_contrat)->toBe(ContractStatut::ManqueSignature);
});

it('onglet Gestion : déclare le dossier non conforme avec un motif', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('declarerNonConforme')->schemaComponent(), data: ['motif' => 'Pièces d\'identité illisibles'])
        ->assertHasNoActionErrors();

    $contract->refresh();
    expect($contract->estNonConforme())->toBeTrue()
        ->and($contract->non_conforme_motif)->toBe('Pièces d\'identité illisibles')
        ->and($contract->non_conforme_at)->not->toBeNull();
});

it('onglet Gestion : annule logiquement le dossier (sans le supprimer)', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('annulerDossier')->schemaComponent(), data: ['motif' => 'Abandon du projet par l\'employeur'])
        ->assertHasNoActionErrors();

    $contract->refresh();
    expect($contract->estAnnule())->toBeTrue()
        ->and($contract->annulation_motif)->toBe('Abandon du projet par l\'employeur')
        // Annulation LOGIQUE : le dossier existe toujours en base.
        ->and(Contract::whereKey($contract->id)->exists())->toBeTrue();
});

it('onglet Gestion : change le type de contrat (apprentissage ↔ professionnalisation)', function () {
    $contract = dossierComplet();
    expect($contract->type_contrat)->toBe(\App\Enums\TypeContrat::Apprentissage);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('changerTypeContrat')->schemaComponent())
        ->assertHasNoActionErrors();

    expect($contract->refresh()->type_contrat)->toBe(\App\Enums\TypeContrat::Professionnalisation);
});

it('onglet Gestion : modifie rapidement les informations de l\'étudiant', function () {
    $contract = dossierComplet();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('modifierEtudiant')->schemaComponent(), data: [
            'email' => 'nouveau@example.com',
            'prenom' => 'Rachid',
            'nom' => 'Benali',
        ])
        ->assertHasNoActionErrors();

    $cand = $contract->candidate->refresh();
    expect($cand->email)->toBe('nouveau@example.com')
        ->and($cand->prenom)->toBe('Rachid')
        ->and($cand->nom)->toBe('Benali');
});

it('onglet Gestion : modifie le contact et le signataire de l\'entreprise', function () {
    $contract = dossierComplet();
    $co = $contract->company;

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction(TestAction::make('modifierContactEntreprise')->schemaComponent(), data: [
            'email' => 'contact@societe.fr', 'prenom' => 'Paul', 'nom' => 'Durand',
        ])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('modifierSignataire')->schemaComponent(), data: [
            'prenom' => 'Sophie', 'nom' => 'Martin', 'email' => 'sophie@societe.fr', 'poste' => 'Gérante',
        ])
        ->assertHasNoActionErrors();

    expect($co->contactPrincipal()->where('email', 'contact@societe.fr')->exists())->toBeTrue()
        ->and($co->representantsLegaux()->where('email', 'sophie@societe.fr')->where('fonction', 'Gérante')->exists())->toBeTrue();
});

it('amorce les repeaters « par année » sur un ancien dossier sans données', function () {
    $contract = dossierComplet();
    expect($contract->remuneration_annuelle)->toBeNull()
        ->and($contract->calendrier_financement)->toBeNull();

    // Ouvrir puis enregistrer un dossier vierge conserve les lignes amorcées.
    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    $contract->refresh();
    expect($contract->remuneration_annuelle)->toHaveCount(1)
        ->and((int) $contract->remuneration_annuelle[0]['annee'])->toBe(1)
        ->and($contract->remuneration_annuelle[0]['base'])->toBe('smic')
        ->and($contract->calendrier_financement)->toHaveCount(3)
        ->and(collect($contract->calendrier_financement)->pluck('annee')->map(fn ($a) => (int) $a)->all())->toBe([1, 2, 3]);
});
