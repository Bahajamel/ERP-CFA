<?php

use App\Cerfa\CerfaApprentissage;
use App\Documents\ConventionFormation;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Vérifie que le CERFA se remplit intégralement quand le dossier est complet :
 * toutes les rubriques (employeur, apprenti, maîtres, contrat, rémunération,
 * formation) portent la bonne valeur. Le PDF est aussi écrit sur disque pour
 * une relecture visuelle.
 */
function dossierCerfaComplet(): Contract
{
    Organisation::courante()->update([
        'raison_sociale' => 'CFA V2S', 'nom' => 'CFA V2S',
        'siret' => '11111111100011', 'numero_uai' => '0011111X', 'nda' => '11223344556',
        'representant_nom' => 'Durand', 'representant_prenom' => 'Claire', 'representant_fonction' => 'Directrice',
        'adresse' => '5 avenue de la République', 'code_postal' => '75011', 'ville' => 'Paris',
    ]);

    $formation = Formation::factory()->create([
        'libelle' => 'Concepteur Développeur d\'Applications', 'code_rncp' => 'RNCP37873',
    ]);

    $cand = \App\Models\Candidate::factory()->create([
        'nom' => 'Benali', 'prenom' => 'Rachid', 'email' => 'rachid.benali@example.com', 'telephone' => '0611223344',
        'date_naissance' => '2005-12-15', 'sexe' => 'M', 'nationalite' => 'francaise',
        'adresse' => '8 rue des Lilas', 'code_postal' => '69007', 'ville' => 'Lyon',
        'lieu_naissance' => 'Lyon', 'departement_naissance' => '69', 'num_secu' => '1051269380012 34',
        'rqth' => false, 'boe' => false, 'aeeh_pch_pps' => false, 'sportif_haut_niveau' => false,
        'projet_creation_entreprise' => false, 'regime_social' => 'general', 'situation_avant_contrat' => 'etudiant',
        'niveau_diplome_max' => 'bac_2', 'diplome_max' => 'bts',
        'niveau_dernier_diplome_prepare' => 'bac', 'dernier_diplome_prepare' => 'bac_general',
        'intitule_dernier_diplome' => 'BTS SIO option SLAM', 'derniere_classe_suivie' => 'cycle_2_validee',
        'repr_legal_nom' => 'Benali', 'repr_legal_prenom' => 'Fatima', 'repr_legal_email' => 'fatima.benali@example.com',
        'repr_legal_adresse' => '8 rue des Lilas', 'repr_legal_code_postal' => '69007', 'repr_legal_ville' => 'Lyon',
    ]);

    $company = Company::factory()->create([
        'raison_sociale' => 'Webtech Solutions', 'siret' => '81234567800012',
        'adresse' => '12 rue de la Paix', 'code_postal' => '75002', 'ville' => 'Paris',
        'code_ape_naf' => '6201Z', 'code_idcc' => '1486', 'convention_collective' => 'Syntec',
        'caisse_retraite' => 'AGIRC-ARRCO', 'nombre_salaries' => 42,
        'type_employeur' => 'entreprise_rcs', 'type_employeur_specifique' => 'groupement_employeurs',
        'secteur_type' => 'prive',
    ]);
    CompanyContact::factory()->create([
        'company_id' => $company->id, 'is_principal' => true,
        'nom' => 'Rousseau', 'prenom' => 'Jean', 'email' => 'rh@webtech.fr', 'telephone' => '0102030405',
    ]);
    $tuteur = CompanyContact::factory()->create([
        'company_id' => $company->id, 'is_tuteur' => true,
        'nom' => 'Marty', 'prenom' => 'Georges', 'email' => 'g.marty@webtech.fr', 'fonction' => 'Lead développeur',
        'date_naissance' => '1985-04-20', 'niveau_diplome' => 'bac_3_4', 'diplome' => 'master',
    ]);

    return Contract::factory()->create([
        'candidate_id' => $cand->id, 'company_id' => $company->id, 'formation_id' => $formation->id,
        'tuteur_id' => $tuteur->id,
        'type_contrat' => 'apprentissage', 'nature_contrat' => 'premier_contrat', 'code_rncp' => 'RNCP37873',
        'mode_contractuel' => 'duree_limitee',
        'derogation' => true, 'type_derogation' => 'reduction_duree',
        'emploi_occupe' => 'Développeur web', 'missions' => 'Développement d\'applications web.',
        'duree_hebdo_heures' => 35, 'duree_hebdo_minutes' => 0,
        'avantage_repas' => 4.50, 'avantage_logement' => 120, 'autres_avantages' => false, 'travail_dangereux' => false,
        'date_debut_contrat' => '2026-09-01', 'date_fin_contrat' => '2028-08-31',
        'date_conclusion' => '2026-08-20', 'date_debut_formation_pratique' => '2026-09-01',
        'date_fin_periode_essai' => '2026-10-15', 'smc' => false,
        'salaire_mensuel_brut' => 977.55, 'pourcentage_smic' => 53, 'cout_formation' => 8000,
        'lieu_execution' => '12 rue de la Paix', 'lieu_execution_code_postal' => '75002', 'lieu_execution_ville' => 'Paris',
        'lieu_formation' => '15 rue Garibaldi', 'lieu_formation_code_postal' => '69003', 'lieu_formation_ville' => 'Lyon',
        'lieu_formation_denomination' => 'Campus Numérique Lyon', 'lieu_formation_uai' => '0692222Y', 'lieu_formation_siret' => '81234567800020',
        'date_debut' => '2026-09-01', 'date_fin' => '2028-08-31',
        'duree_formation_heures' => 1400, 'heures_elearning' => 200, 'heures_classe_virtuelle' => 50,
        'statut_contrat' => 'en_cours',
    ]);
}

it('remplit intégralement le CERFA depuis un dossier complet', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);
    $this->actingAs($user);

    $contract = dossierCerfaComplet();
    $cerfa = app(CerfaApprentissage::class);

    $champs = $cerfa->champs($contract);

    expect($champs['employeur_denomination'])->toBe('Webtech Solutions')
        ->and($champs['employeur_prive'])->toBeTrue()
        ->and($champs['employeur_public'])->toBeFalse()
        ->and($champs['employeur_type'])->toBe('12')
        ->and($champs['employeur_specifique'])->toBe('2')
        ->and($champs['employeur_ape'])->toBe('6201Z')
        ->and($champs['employeur_idcc'])->toBe('1486')
        ->and($champs['employeur_effectif'])->toBe('42')
        ->and($champs['apprenti_nom_naissance'])->toBe('Benali')
        ->and($champs['apprenti_prenom'])->toBe('Rachid')
        ->and($champs['apprenti_sexe_m'])->toBeTrue()
        ->and($champs['apprenti_nationalite'])->toBe('1')
        ->and($champs['apprenti_regime_social'])->toBe('2')
        ->and($champs['apprenti_situation_avant'])->toBe('3')
        ->and($champs['apprenti_diplome_max'])->toBe('54')
        ->and($champs['apprenti_dernier_diplome'])->toBe('42')
        ->and($champs['apprenti_derniere_classe'])->toBe('21')
        ->and($champs['apprenti_dept_naissance'])->toBe('69')
        ->and($champs['apprenti_naiss_jj'])->toBe('15')
        ->and($champs['maitre1_nom'])->toBe('Marty')
        ->and($champs['maitre1_naiss_aaaa'])->toBe('1985')
        ->and($champs['date_debut_jj'])->toBe('01')
        ->and($champs['date_debut_aaaa'])->toBe('2026')
        ->and($champs['duree_hebdo_heures'])->toBe('35')
        ->and($champs['mode_contractuel'])->toBe('1')
        ->and($champs['type_contrat_avenant'])->toBe('11')
        ->and($champs['type_derogation'])->toBe('21')
        ->and($champs['caisse_retraite'])->toBe('AGIRC-ARRCO')
        ->and($champs['avantage_repas_euros'])->toBe('4')
        ->and($champs['avantage_repas_cents'])->toBe('50')
        ->and($champs['salaire_brut_euros'])->toBe('977')
        ->and($champs['salaire_brut_cents'])->toBe('55')
        ->and($champs['code_rncp'])->toBe('RNCP37873')
        ->and($champs['diplome_vise'])->toBe('Concepteur Développeur d\'Applications')
        ->and($champs['cfa_siret'])->toBe('111 111 111 00011')
        ->and($champs['cfa_lieu_principal'])->toBeFalse()
        ->and($champs['lieu_formation_commune'])->toBe('Lyon')
        ->and($champs['lieu_formation_denomination'])->toBe('Campus Numérique Lyon')
        ->and($champs['lieu_formation_uai'])->toBe('0692222Y')
        ->and($champs['lieu_formation_siret'])->toBe('812 345 678 00020')
        ->and($champs['repres_nom_prenom'])->toBe('Benali Fatima')
        ->and($champs['repres_courriel'])->toBe('fatima.benali@example.com')
        ->and($champs['repres_adr_commune'])->toBe('Lyon')
        // Rémunération : changement d'âge (20 → 21 ans au 01/01/2027) → 2 périodes en année 1.
        ->and($champs['rem1a_pct'])->not->toBeNull()
        ->and($champs['rem1b_pct'])->not->toBeNull()
        // Maître d'apprentissage : diplôme (libellé) + niveau (cadre européen).
        ->and($champs['maitre1_diplome'])->toBe('Master')
        ->and($champs['maitre1_niveau'])->toBe('6');

    // Le PDF se génère réellement (overlay FPDI sur le modèle officiel).
    expect(strlen($cerfa->pour($contract)))->toBeGreaterThan(1000);
});

it('génère la convention (modèle Filiz) depuis un dossier complet', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);
    $this->actingAs($user);

    $contract = dossierCerfaComplet();
    $contract->update([
        'calendrier_financement' => [
            ['annee' => 1, 'formation' => 8000, 'financement' => 8000, 'geste_commercial' => 0, 'reste_a_charge' => 0],
        ],
        'frais_hebergement' => true, 'frais_restauration' => false, 'frais_equipement' => true, 'frais_mobilite' => false,
    ]);

    $service = app(ConventionFormation::class);
    $d = $service->donnees($contract);

    expect($d['cfa_designation'])->toBe('CFA V2S')
        ->and($d['cfa_representant'])->toBe('Claire Durand')
        ->and($d['cfa_representant_qualite'])->toBe('Directrice')
        ->and($d['entreprise_designation'])->toBe('Webtech Solutions')
        ->and($d['entreprise_idcc'])->toBe('1486')
        ->and($d['entreprise_convention_collective'])->toBe('Syntec')
        ->and($d['formation_intitule'])->toBe('Concepteur Développeur d\'Applications')
        ->and($d['modalites'])->toBe('Mixte (présentiel et à distance)')
        ->and($d['maitre_nom'])->toBe('Marty')
        ->and($d['apprenti_nom'])->toBe('Benali')
        ->and($d['financement'][0]['prestation'])->toBe(8000)
        ->and($d['frais_hebergement'])->toBeTrue()
        ->and($d['cout_total'])->toBe('8 000,00 €');

    // Le PDF (modèle Filiz, dompdf) se génère réellement.
    expect(strlen($service->pour($contract)))->toBeGreaterThan(1000);
});

/*
|--------------------------------------------------------------------------
| Numéro de voirie : colonne dédiée vs extraction
|--------------------------------------------------------------------------
*/

it('remplit les cases N° du CERFA depuis les colonnes numéro dédiées', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);
    $this->actingAs($user);

    $contract = dossierCerfaComplet();

    // Saisie séparée (autocomplétion BAN corrigée) : la voie ne contient
    // plus le numéro, qui vit dans sa propre colonne.
    $contract->update([
        'lieu_formation' => 'Rue Charles de Gaulle', 'lieu_formation_numero' => '228',
        'lieu_execution' => 'Rue de la Paix', 'lieu_execution_numero' => '12',
    ]);

    $champs = app(CerfaApprentissage::class)->champs($contract->fresh());

    expect($champs['lieu_formation_num'])->toBe('228')
        ->and($champs['lieu_formation_voie'])->toBe('Rue Charles de Gaulle')
        ->and($champs['employeur_adr_num'])->toBe('12')
        ->and($champs['employeur_adr_voie'])->toBe('Rue de la Paix');
});

it('extrait encore le numéro des adresses saisies en un seul champ', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);
    $this->actingAs($user);

    // Le fixture stocke « 15 rue Garibaldi » sans colonne numéro : repli.
    $champs = app(CerfaApprentissage::class)->champs(dossierCerfaComplet());

    expect($champs['lieu_formation_num'])->toBe('15')
        ->and($champs['lieu_formation_voie'])->toBe('rue Garibaldi')
        ->and($champs['apprenti_adr_num'])->toBe('8')
        ->and($champs['apprenti_adr_voie'])->toBe('rue des Lilas');
});

it('n\'imprime pas deux fois le numéro quand la voie le répète', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);
    $this->actingAs($user);

    $contract = dossierCerfaComplet();

    // Fiche historique : le numéro vit à la fois dans sa colonne et en tête
    // de la voie (cas réel des entreprises importées avant la séparation).
    $contract->company->update(['numero_siege' => '228', 'adresse' => '228 rue de Charenton']);
    $contract->update(['lieu_execution' => null, 'lieu_execution_code_postal' => null, 'lieu_execution_ville' => null]);

    $champs = app(CerfaApprentissage::class)->champs($contract->fresh());

    expect($champs['employeur_adr_num'])->toBe('228')
        ->and($champs['employeur_adr_voie'])->toBe('rue de Charenton');
});
