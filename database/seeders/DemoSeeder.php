<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatut;
use App\Enums\AvailabilityType;
use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Enums\InvoiceStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Enums\QualiopiStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Enums\SignatureRequestStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\FinanceLine;
use App\Models\FinancePayment;
use App\Models\Formation;
use App\Models\Invoice;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Opco;
use App\Models\OpcoFile;
use App\Models\QualiopiIndicator;
use App\Models\Rupture;
use App\Models\SignatureRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\SignatureService;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // -------- Utilisateurs & rôles --------
        $admin = User::firstOrCreate(['email' => 'admin@cfa-v2s.fr'], ['name' => 'Admin', 'password' => Hash::make('password')]);
        $admin->syncRoles('Administrateur');

        $direction = $this->user('Claire Dubois', 'direction@cfa-v2s.fr', 'Direction');
        $sophie = $this->user('Sophie Martin', 'sophie.martin@cfa-v2s.fr', 'Commercial');
        $thomas = $this->user('Thomas Bernard', 'thomas.bernard@cfa-v2s.fr', 'Commercial');
        $admission = $this->user('Nadia Lefèvre', 'admission@cfa-v2s.fr', 'Admission');
        $administratif = $this->user('Karim Benali', 'administratif@cfa-v2s.fr', 'Administratif');
        $finance = $this->user('Julie Moreau', 'finance@cfa-v2s.fr', 'Finance');

        // -------- Référentiels --------
        $opcoAtlas = Opco::firstOrCreate(['nom' => 'OPCO Atlas']);
        $opcoEp = Opco::firstOrCreate(['nom' => 'OPCO EP']);
        $opco2i = Opco::firstOrCreate(['nom' => 'OPCO 2i']);
        $akto = Opco::firstOrCreate(['nom' => 'AKTO']);

        $devWeb = Formation::firstOrCreate(['libelle' => 'Concepteur Développeur d\'Applications'], ['code_rncp' => 'RNCP37873', 'niveau' => 'Bac+3/4', 'duree_mois' => 18, 'rythme_defaut' => '1 sem. / 3 sem.']);
        $cyber = Formation::firstOrCreate(['libelle' => 'Administrateur Systèmes & Réseaux'], ['code_rncp' => 'RNCP34061', 'niveau' => 'Bac+2', 'duree_mois' => 24, 'rythme_defaut' => '2 j. / 3 j.']);
        $commerce = Formation::firstOrCreate(['libelle' => 'Négociation et Digitalisation de la Relation Client'], ['code_rncp' => 'RNCP38368', 'niveau' => 'Bac+2', 'duree_mois' => 24, 'rythme_defaut' => '2 j. / 3 j.']);
        $marketing = Formation::firstOrCreate(['libelle' => 'Responsable Marketing Digital'], ['code_rncp' => 'RNCP36603', 'niveau' => 'Bac+3', 'duree_mois' => 12, 'rythme_defaut' => '1 sem. / 3 sem.']);
        $compta = Formation::firstOrCreate(['libelle' => 'Gestionnaire Comptable et Fiscal'], ['code_rncp' => 'RNCP35521', 'niveau' => 'Bac+2', 'duree_mois' => 24, 'rythme_defaut' => '1 j. / 4 j.']);

        // -------- Entreprises + contacts --------
        $companies = [];
        $companiesData = [
            ['Webtech Solutions', 'Webtech', '81234567800012', 'Numérique / ESN', $opcoAtlas, CompanyStatut::Partenaire],
            ['Boulangerie Au Bon Pain', 'Au Bon Pain', '52345678900023', 'Artisanat / Alimentaire', $opcoEp, CompanyStatut::Active],
            ['IndusMétal SAS', 'IndusMétal', '34256789000034', 'Industrie / Métallurgie', $opco2i, CompanyStatut::Partenaire],
            ['Distri Plus', 'Distri+', '49856712300045', 'Commerce / Distribution', $akto, CompanyStatut::Active],
            ['Cabinet Compta Conseil', 'CCC', '60123498700056', 'Services / Comptabilité', $opcoAtlas, CompanyStatut::Prospect],
        ];
        foreach ($companiesData as [$rs, $nc, $siret, $secteur, $opco, $statut]) {
            $c = Company::create([
                'raison_sociale' => $rs, 'nom_commercial' => $nc, 'siret' => $siret,
                'adresse' => fake()->streetAddress().', '.fake()->postcode().' '.fake()->city(),
                'secteur' => $secteur, 'opco_id' => $opco->id, 'statut' => $statut,
            ]);
            $principal = CompanyContact::create([
                'company_id' => $c->id, 'nom' => fake()->lastName(), 'prenom' => fake()->firstName(),
                'email' => fake()->companyEmail(), 'telephone' => fake()->phoneNumber(),
                'fonction' => 'Responsable RH', 'is_principal' => true, 'is_tuteur' => false,
            ]);
            $tuteur = CompanyContact::create([
                'company_id' => $c->id, 'nom' => fake()->lastName(), 'prenom' => fake()->firstName(),
                'email' => fake()->companyEmail(), 'telephone' => fake()->phoneNumber(),
                'fonction' => "Maître d'apprentissage", 'is_principal' => false, 'is_tuteur' => true,
            ]);
            $companies[] = ['model' => $c, 'principal' => $principal, 'tuteur' => $tuteur];
        }

        // -------- Besoins entreprises --------
        $needsData = [
            [0, 'Développeur web alternant', $devWeb, NeedStatut::CandidatRetenu, 1],
            [2, 'Technicien réseaux alternant', $cyber, NeedStatut::ProfilsEnvoyes, 2],
            [3, 'Conseiller de vente alternant', $commerce, NeedStatut::EntretienPrevu, 1],
            [0, 'Chargé de marketing digital', $marketing, NeedStatut::ProfilsRecherches, 1],
            [4, 'Assistant comptable alternant', $compta, NeedStatut::Cree, 1],
            // Créé « Candidat retenu » : on le clôturera à « Pourvu » via la machine
            // à états après avoir enregistré le candidat accepté (P0-04-3 / P0-05-5).
            [1, 'Vendeur en boulangerie', $commerce, NeedStatut::CandidatRetenu, 1],
        ];
        $needs = [];
        foreach ($needsData as [$ci, $poste, $formation, $statut, $nb]) {
            $needs[] = Need::create([
                'company_id' => $companies[$ci]['model']->id,
                'intitule_poste' => $poste,
                'formation_id' => $formation->id,
                'localisation' => fake()->city(),
                'date_demarrage' => now()->addMonths(2)->startOfMonth(),
                'nb_postes' => $nb,
                'rythme' => $formation->rythme_defaut,
                'prerequis' => 'Niveau requis : '.$formation->niveau.'. Motivation et sérieux.',
                'contact_id' => $companies[$ci]['principal']->id,
                'tuteur_id' => $companies[$ci]['tuteur']->id,
                'statut' => $statut,
            ]);
        }

        // -------- Candidats --------
        $commerciaux = [$sophie->id, $thomas->id];
        // Cycle apprenant : Entretien à planifier → Entretien prévu → Accepté /
        // Refusé. Les candidats engagés dans le parcours aval sont « Accepté » ;
        // Nathan démarre « à planifier » (son entretien planifié plus bas le
        // fera passer automatiquement à « Entretien prévu »).
        $candidatesData = [
            ['Lucas', 'Petit', $devWeb, CandidateStatut::Accepte, 'Bac+2'],
            ['Emma', 'Roux', $cyber, CandidateStatut::Accepte, 'Bac'],
            ['Hugo', 'Fontaine', $commerce, CandidateStatut::Accepte, 'Bac'],
            ['Léa', 'Girard', $marketing, CandidateStatut::Accepte, 'Bac+2'],
            ['Nathan', 'Lambert', $compta, CandidateStatut::EntretienAPlanifier, 'Bac'],
            ['Chloé', 'Mercier', $devWeb, CandidateStatut::Accepte, 'Bac+2'],
            ['Maxime', 'Blanc', $cyber, CandidateStatut::Accepte, 'Bac'],
            ['Sarah', 'Faure', $commerce, CandidateStatut::Accepte, 'Bac'],
            ['Théo', 'Garnier', $marketing, CandidateStatut::Refuse, 'Bac+2'],
            ['Inès', 'Chevalier', $compta, CandidateStatut::Accepte, 'Bac'],
            // Pipeline de recrutement en amont (démo du cycle Entretiens) : ces
            // candidats ne sont pas encore engagés, pour peupler les colonnes
            // « Entretien à planifier » / « Entretien prévu » du pipeline.
            ['Jade', 'Renard', $devWeb, CandidateStatut::EntretienAPlanifier, 'Bac'],
            ['Camille', 'Girard', $cyber, CandidateStatut::EntretienAPlanifier, 'Bac+2'],
            ['Noah', 'Lefevre', $commerce, CandidateStatut::EntretienAPlanifier, 'Bac'],
            ['Manon', 'Robert', $marketing, CandidateStatut::EntretienAPlanifier, 'Bac+2'],
            ['Louna', 'Simon', $compta, CandidateStatut::EntretienAPlanifier, 'Bac'],
        ];
        $candidates = [];
        foreach ($candidatesData as $i => [$prenom, $nom, $formation, $statut, $niveau]) {
            $candidates[] = Candidate::create([
                'nom' => $nom, 'prenom' => $prenom,
                'email' => strtolower($prenom.'.'.$nom).'@email.fr',
                'telephone' => fake()->phoneNumber(),
                'date_naissance' => fake()->dateTimeBetween('-25 years', '-18 years'),
                'adresse' => fake()->streetAddress().', '.fake()->postcode().' '.fake()->city(),
                'formation_visee_id' => $formation->id,
                'niveau_actuel' => $niveau,
                'mobilite' => fake()->randomElement(['Permis B + véhicule', 'Transports en commun', 'Mobile région']),
                'disponibilite' => 'Septembre',
                'source' => fake()->randomElement(['Salon', 'Site web', 'Recommandation', 'Pôle Emploi', 'Réseaux sociaux']),
                'commercial_id' => $commerciaux[$i % 2],
                'statut' => $statut,
            ]);
        }

        // Pièces obligatoires du formulaire de candidature (étape 1 du cycle) :
        // tout candidat déposé possède déjà pièce d'identité, CV et carte vitale.
        // On les crée pour la démo afin de refléter la réalité (jamais de dossier
        // « incomplet » sur un candidat existant).
        foreach ($candidates as $candidat) {
            $this->doterPiecesCandidature($candidat);
        }

        // -------- Entretiens (nouvelle section du cycle) --------
        // Nathan : entretien planifié → il passe automatiquement à « Entretien prévu ».
        $candidates[4]->entretiens()->create([
            'responsable_id' => $sophie->id,
            'date_entretien' => now()->addDays(3)->toDateString(),
            'heure_debut' => '10:00', 'heure_fin' => '11:00',
            'mode' => EntretienMode::Presentiel->value,
            'statut' => EntretienStatut::Planifie->value,
        ]);
        // Lucas : entretien réalisé et accepté (trace du parcours).
        $candidates[0]->entretiens()->create([
            'responsable_id' => $sophie->id,
            'date_entretien' => now()->subDays(45)->toDateString(),
            'heure_debut' => '14:00', 'heure_fin' => '15:00',
            'mode' => EntretienMode::Visio->value, 'lien_visio' => 'https://meet.exemple.fr/lucas-petit',
            'statut' => EntretienStatut::Realise->value, 'resultat' => 'accepte',
            'compte_rendu' => 'Très motivé, projet professionnel clair. Avis favorable.',
        ]);
        // Théo : entretien réalisé et refusé.
        $candidates[8]->entretiens()->create([
            'responsable_id' => $thomas->id,
            'date_entretien' => now()->subDays(30)->toDateString(),
            'heure_debut' => '09:00', 'heure_fin' => '09:45',
            'mode' => EntretienMode::Telephone->value,
            'statut' => EntretienStatut::Realise->value, 'resultat' => 'refuse',
            'compte_rendu' => 'Projet incompatible avec le rythme de l\'alternance.',
        ]);
        // Camille & Noah : entretiens planifiés → ils passent « Entretien prévu ».
        $candidates[11]->entretiens()->create([
            'responsable_id' => $sophie->id,
            'date_entretien' => now()->addDays(2)->toDateString(),
            'heure_debut' => '09:30', 'heure_fin' => '10:15',
            'mode' => EntretienMode::Visio->value, 'lien_visio' => 'https://meet.exemple.fr/camille-girard',
            'statut' => EntretienStatut::Planifie->value,
        ]);
        $candidates[12]->entretiens()->create([
            'responsable_id' => $thomas->id,
            'date_entretien' => now()->addDays(4)->toDateString(),
            'heure_debut' => '15:00', 'heure_fin' => '16:00',
            'mode' => EntretienMode::Presentiel->value,
            'statut' => EntretienStatut::Planifie->value,
        ]);
        // Manon : entretien réalisé, décision non encore prise (à traiter).
        $candidates[13]->entretiens()->create([
            'responsable_id' => $sophie->id,
            'date_entretien' => now()->subDay()->toDateString(),
            'heure_debut' => '11:00', 'heure_fin' => '11:45',
            'mode' => EntretienMode::Telephone->value,
            'statut' => EntretienStatut::Realise->value,
            'compte_rendu' => 'Bon échange, à confronter avec les besoins entreprises.',
        ]);
        // Louna reste « Entretien à planifier » (aucun créneau encore posé).

        // -------- Matching (propositions candidat ↔ besoin) --------
        Matching::create(['need_id' => $needs[0]->id, 'candidate_id' => $candidates[0]->id, 'statut' => MatchingStatut::Accepte, 'cv_envoye' => true, 'date_entretien' => now()->subDays(20), 'retour_entreprise' => 'Candidat retenu, profil parfait.', 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[1]->id, 'statut' => MatchingStatut::PropositionEnvoyee, 'cv_envoye' => true, 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[6]->id, 'statut' => MatchingStatut::PropositionEnvoyee, 'cv_envoye' => true, 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[2]->id, 'candidate_id' => $candidates[2]->id, 'statut' => MatchingStatut::EntretienEntreprise, 'cv_envoye' => true, 'date_entretien' => now()->addDays(5), 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[3]->id, 'candidate_id' => $candidates[3]->id, 'statut' => MatchingStatut::EnRecherche, 'cv_envoye' => false, 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[5]->id, 'candidate_id' => $candidates[7]->id, 'statut' => MatchingStatut::Accepte, 'cv_envoye' => true, 'date_entretien' => now()->subDays(30), 'retour_entreprise' => 'Embauché.', 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[3]->id, 'statut' => MatchingStatut::Refuse, 'cv_envoye' => true, 'retour_entreprise' => 'Profil non retenu.', 'assigned_by' => $thomas->id]);

        // Le besoin « Vendeur en boulangerie » a un candidat accepté : on le clôture
        // proprement en « Pourvu » (date de clôture + cascade des pistes ouvertes).
        $needs[5]->transitionTo(NeedStatut::Pourvu);

        // -------- CV des candidats (pièce portée par la fiche candidat) --------
        // Les admissions officielles naissent plus bas, automatiquement, dès que
        // le dossier OPCO d'un contrat signé est créé/transmis (cycle apprenant).
        $avecCv = [0, 1, 2, 3, 5, 6, 7, 9];
        foreach ($avecCv as $i) {
            $this->attacherCvDemo($candidates[$i]);
        }

        // Quelques disponibilités structurées (démo de la nouvelle fonctionnalité).
        $candidates[1]->availabilities()->create([
            'type' => AvailabilityType::Disponible->value, 'immediate' => true,
            'commentaire' => 'Disponible immédiatement',
        ]);
        $candidates[3]->availabilities()->create([
            'type' => AvailabilityType::Disponible->value,
            'date_debut' => now()->addMonth()->startOfMonth()->toDateString(),
            'commentaire' => 'Après ses examens',
        ]);

        // -------- Contrats --------
        // Contrat 0 : signé + transmis OPCO, dossier OPCO REJETÉ → en correction (bloqué)
        $contrat0 = Contract::create([
            'candidate_id' => $candidates[0]->id, 'company_id' => $companies[0]['model']->id, 'formation_id' => $devWeb->id,
            'code_rncp' => $devWeb->code_rncp, 'date_debut' => now()->addMonth()->startOfMonth(), 'date_fin' => now()->addMonths(19)->startOfMonth(),
            'tuteur_id' => $companies[0]['tuteur']->id, 'rythme' => $devWeb->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Complet,
        ]);
        // Contrat 1 : signé + actif, OPCO accepté
        $contrat1 = Contract::create([
            'candidate_id' => $candidates[7]->id, 'company_id' => $companies[1]['model']->id, 'formation_id' => $commerce->id,
            'code_rncp' => $commerce->code_rncp, 'date_debut' => now()->subMonths(2)->startOfMonth(), 'date_fin' => now()->addMonths(22)->startOfMonth(),
            'tuteur_id' => $companies[1]['tuteur']->id, 'rythme' => $commerce->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Complet,
        ]);
        // Contrat 2 : en préparation (brouillon)
        $contrat2 = Contract::create([
            'candidate_id' => $candidates[2]->id, 'company_id' => $companies[3]['model']->id, 'formation_id' => $commerce->id,
            'code_rncp' => $commerce->code_rncp, 'date_debut' => now()->addMonths(2)->startOfMonth(),
            'tuteur_id' => $companies[3]['tuteur']->id, 'rythme' => $commerce->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::NonSigne, 'statut_contrat' => ContractStatut::EnCours,
        ]);
        // Contrat 3 : envoyé pour signature
        $contrat3 = Contract::create([
            'candidate_id' => $candidates[5]->id, 'company_id' => $companies[0]['model']->id, 'formation_id' => $devWeb->id,
            'code_rncp' => $devWeb->code_rncp, 'date_debut' => now()->addMonths(2)->startOfMonth(),
            'tuteur_id' => $companies[0]['tuteur']->id, 'rythme' => $devWeb->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Envoye, 'statut_contrat' => ContractStatut::ManqueSignature,
        ]);

        // -------- Dossiers OPCO --------
        // Bloqué : rejeté puis en correction
        $opcoBloque = OpcoFile::create([
            'contract_id' => $contrat0->id, 'opco_id' => $opcoAtlas->id, 'date_depot' => now()->subDays(12),
            'statut' => OpcoStatut::EnCorrection, 'montant_prevu' => 9200, 'motif_rejet' => 'NIR du salarié erroné sur le CERFA.',
            'responsable_correction_id' => $administratif->id, 'date_relance' => now()->addDays(3),
            'commentaire_interne' => 'Correction du CERFA en cours, redépôt prévu cette semaine.',
        ]);
        // Accepté
        OpcoFile::create([
            'contract_id' => $contrat1->id, 'opco_id' => $opcoEp->id, 'date_depot' => now()->subMonths(2),
            'statut' => OpcoStatut::Accepte, 'montant_prevu' => 8000, 'montant_accepte' => 8000,
        ]);
        // NB : pas de dossier OPCO pour le contrat 3 — il n'est pas encore signé
        // par les trois parties (règle du cycle : signature avant dossier OPCO).

        // -------- Admissions officielles --------
        // Les dossiers OPCO ci-dessus (créés/transmis) ont ouvert automatiquement
        // les admissions « À vérifier ». On valide celle du contrat actif.
        $contrat1->refresh()->admission->forceFill([
            'statut' => AdmissionStatut::Valide->value, 'validated_by' => $admission->id,
            'validated_at' => now()->subDays(25), 'commentaire' => 'Dossier contrôlé, admission validée.',
        ])->save();

        // -------- Tâches & alertes --------
        $tasksData = [
            ['Relancer l\'entreprise Webtech pour la signature', $sophie->id, TaskPriorite::Haute, TaskStatut::AFaire, now()->addDays(2), $contrat3],
            ['Corriger le CERFA (NIR erroné) – dossier OPCO Atlas', $administratif->id, TaskPriorite::Urgente, TaskStatut::EnCours, now()->subDay(), $contrat0],
            ['Compléter la pièce manquante (CERFA) – dossier Hugo Fontaine', $admission->id, TaskPriorite::Normale, TaskStatut::AFaire, now()->addDays(3), $candidates[2]],
            ['Appeler Nathan Lambert – planifier son entretien', $thomas->id, TaskPriorite::Normale, TaskStatut::EnRetard, now()->subDays(2), $candidates[4]],
            ['Préparer l\'entretien entreprise – Distri+', $sophie->id, TaskPriorite::Normale, TaskStatut::AFaire, now()->addDays(5), $needs[2]],
            ['Vérifier le dossier d\'admission de Chloé Mercier', $admission->id, TaskPriorite::Haute, TaskStatut::EnAttente, now()->addDay(), $candidates[5]],
            ['Suivre l\'accompagnement rupture – Inès Chevalier', $direction->id, TaskPriorite::Haute, TaskStatut::EnCours, now()->addDays(7), $candidates[9]],
            ['Facturer le contrat actif – Sarah Faure', $finance->id, TaskPriorite::Basse, TaskStatut::AFaire, now()->addDays(10), $contrat1],
        ];
        foreach ($tasksData as [$titre, $assignee, $priorite, $statut, $due, $taskable]) {
            Task::create([
                'titre' => $titre,
                'taskable_type' => $taskable::class,
                'taskable_id' => $taskable->id,
                'assignee_id' => $assignee,
                'created_by' => $direction->id,
                'due_date' => $due,
                'priorite' => $priorite,
                'statut' => $statut,
                'source' => 'manuel',
            ]);
        }

        // ============================================================
        //  Différenciateurs métier — pour que chaque tableau de bord parle
        // ============================================================
        $qualite = $this->user('Awa Diallo', 'qualite@cfa-v2s.fr', 'Qualité');

        // ---- Finance : lignes financières par contrat ----
        // NB : les contrats dont l'OPCO est accepté génèrent DÉJÀ leur ligne
        // financière automatiquement (FinanceService, à l'acceptation). On ne
        // crée donc à la main que le cas non couvert : le dossier bloqué, dont
        // l'OPCO n'est pas accepté. En créer une pour $contrat1 ferait doublon
        // avec la ligne auto et gonflerait le « Montant attendu » de 8 000 €.
        FinanceLine::create([
            'contract_id' => $contrat0->id,
            // Rattachée au dossier OPCO qui la bloque : sans ce lien, le cockpit
            // annonçait « 9 200 € bloqués » et « 0 dossier OPCO bloqué » — le
            // montant sans le dossier à aller débloquer.
            'opco_file_id' => $opcoBloque->id,
            'libelle' => 'Financement OPCO — année 1',
            'montant_attendu' => 9200, 'montant_bloque' => 9200,
            'motif_blocage' => 'Dossier OPCO en correction (NIR erroné sur le CERFA).',
        ]);

        // ---- Qualiopi : quelques non-conformités à traiter (audit-ready) ----
        QualiopiIndicator::query()->orderBy('numero')->take(3)->get()
            ->each(fn (QualiopiIndicator $i) => $i->update([
                'statut' => QualiopiStatut::NonConforme->value,
                'responsable_id' => $qualite->id,
                'commentaire' => 'Preuve à recollecter avant le prochain audit.',
                'reviewed_at' => now()->subWeek(),
            ]));

        // ---- Rupture : dossier en accompagnement, reclassement en cours ----
        $contratRompu = Contract::create([
            'candidate_id' => $candidates[9]->id, 'company_id' => $companies[3]['model']->id, 'formation_id' => $compta->id,
            'code_rncp' => $compta->code_rncp, 'date_debut' => now()->subMonths(4)->startOfMonth(), 'date_fin' => now()->addMonths(20)->startOfMonth(),
            'tuteur_id' => $companies[3]['tuteur']->id, 'rythme' => $compta->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Rompu,
        ]);
        // Financement du contrat rompu (accepté avant la rupture) : ouvre aussi
        // l'admission officielle, que la rupture basculera en « Rupture ».
        OpcoFile::create([
            'contract_id' => $contratRompu->id, 'opco_id' => $akto->id, 'date_depot' => now()->subMonths(3),
            'statut' => OpcoStatut::Accepte, 'montant_prevu' => 7400, 'montant_accepte' => 7400,
        ]);
        Rupture::create([
            'contract_id' => $contratRompu->id,
            'date_rupture' => now()->subDays(18),
            'motif' => RuptureMotif::Licenciement->value,
            'initiative' => 'Employeur',
            'statut' => RuptureStatut::ATraiter->value,
            'commentaire' => "Réorganisation de l'entreprise, poste supprimé.",
            'created_by' => $direction->id,
        ]);

        // ---- Signature électronique : demande en cours (apprenti a déjà signé) ----
        SignatureRequest::create([
            'contract_id' => $contrat3->id,
            'provider' => 'simulation',
            'external_id' => 'SIMU-DEMO-'.$contrat3->id,
            'statut' => SignatureRequestStatut::PartiellementSignee->value,
            'signataires' => [
                ['role' => SignatureService::ROLE_APPRENTI, 'libelle' => 'Apprenti', 'nom' => $candidates[5]->nom_complet, 'email' => $candidates[5]->email, 'ordre' => 1, 'signe_at' => now()->subDay()->toIso8601String()],
                ['role' => SignatureService::ROLE_EMPLOYEUR, 'libelle' => 'Employeur', 'nom' => $companies[0]['tuteur']->nom_complet, 'email' => $companies[0]['tuteur']->email, 'ordre' => 2, 'signe_at' => null],
                ['role' => SignatureService::ROLE_CFA, 'libelle' => 'CFA', 'nom' => 'Direction CFA', 'email' => 'direction@cfa-v2s.fr', 'ordre' => 3, 'signe_at' => null],
            ],
            'sent_at' => now()->subDays(2),
        ]);

        // ---- Apprenti avec un dossier OPCO rejeté (à corriger) ----
        $yanis = Candidate::create([
            'nom' => 'Moreau', 'prenom' => 'Yanis', 'email' => 'yanis.moreau@email.fr',
            'telephone' => fake()->phoneNumber(), 'date_naissance' => fake()->dateTimeBetween('-22 years', '-18 years'),
            'formation_visee_id' => $cyber->id, 'niveau_actuel' => 'Bac', 'disponibilite' => 'Immédiate',
            'commercial_id' => $thomas->id, 'statut' => CandidateStatut::Accepte,
        ]);
        $this->doterPiecesCandidature($yanis);
        $contratRisque = Contract::create([
            'candidate_id' => $yanis->id, 'company_id' => $companies[2]['model']->id, 'formation_id' => $cyber->id,
            'code_rncp' => $cyber->code_rncp, 'date_debut' => now()->subMonths(3)->startOfMonth(), 'date_fin' => now()->addMonths(21)->startOfMonth(),
            'tuteur_id' => null, 'rythme' => $cyber->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Complet,
        ]);
        OpcoFile::create([
            'contract_id' => $contratRisque->id, 'opco_id' => $opco2i->id, 'date_depot' => now()->subMonths(2),
            'statut' => OpcoStatut::Rejete, 'montant_prevu' => 8600, 'motif_rejet' => 'Pièces justificatives incomplètes.',
        ]);

        $this->facturer($admin);
        $this->doterContratsSignes($admin);
    }

    /**
     * Dote chaque contrat marqué « signé » de son contrat signé en GED.
     *
     * Sans cela, la démo affirmait qu'un contrat était signé sans la moindre
     * pièce au dossier — Yanis Moreau, contrat « complet / signé », zéro
     * document. L'application interdit d'ailleurs de marquer un contrat signé
     * sans preuve (« un document contractuel est requis ») : le seeder écrivait
     * le statut directement et contournait la règle.
     */
    private function doterContratsSignes(User $admin): void
    {
        Contract::query()
            ->where('statut_signature', ContractSignatureStatut::Signe->value)
            ->each(function (Contract $contract) use ($admin): void {
                $document = $contract->documents()->firstOrCreate(
                    ['type' => DocumentType::Contrat->value],
                    [
                        'statut' => DocumentStatut::Recu->value,
                        'source' => DocumentSource::Manuel->value,
                        'nom_fichier' => 'Contrat signé — '.($contract->candidate?->nom_complet ?? "contrat {$contract->id}"),
                        'uploaded_by' => $admin->id,
                    ],
                );

                if ($document->getFirstMedia('fichier') === null) {
                    $document->addMediaFromString(self::pdfDemo())
                        ->usingFileName('contrat-signe-'.$contract->id.'.pdf')
                        ->toMediaCollection('fichier');
                }
            });
    }

    /**
     * Facturation de démonstration : factures + encaissements adossés aux lignes
     * financières réelles (donc aux vrais contrats/entreprises/OPCO).
     *
     * Sans ces données, le dashboard Finance se contredisait : les cartes
     * affichaient « 0 € facturé / 0 € encaissé » tandis que le repli de
     * démonstration de FinanceDashboardData listait juste en dessous cinq
     * factures fictives (« Restaurant Alpha »…), émises à des sociétés
     * introuvables partout ailleurs dans l'outil.
     *
     * L'histoire racontée à l'écran : un acompte encaissé, un solde échu à
     * relancer, une facture partiellement réglée, et rien sur le dossier bloqué
     * (on ne facture pas un financement en correction).
     */
    private function facturer(User $admin): void
    {
        $lignes = FinanceLine::query()
            ->where('montant_bloque', '<=', 0)
            ->whereNotNull('opco_file_id')
            ->with('opcoFile.opco')
            ->orderBy('id')
            ->get();

        if ($lignes->isEmpty()) {
            return;
        }

        $destinataire = fn (FinanceLine $l): string => $l->opcoFile?->opco?->nom ?? 'OPCO';

        // 1) Ligne principale : acompte encaissé + solde échu impayé (à relancer).
        $principale = $lignes->first();
        $attendu = (float) $principale->montant_attendu;

        $acompte = Invoice::create([
            'finance_line_id' => $principale->id,
            'numero' => 'FAC-2026-001',
            'statut' => InvoiceStatut::Payee->value,
            'destinataire' => $destinataire($principale),
            'montant' => round($attendu * 0.3, 2),
            'date_emission' => now()->subMonths(2)->toDateString(),
            'date_echeance' => now()->subMonths(1)->toDateString(),
            'commentaire' => 'Acompte de 30 % à l\'entrée en formation.',
            'created_by' => $admin->id,
        ]);
        FinancePayment::create([
            'finance_line_id' => $principale->id,
            'invoice_id' => $acompte->id,
            'montant' => $acompte->montant,
            'date_paiement' => now()->subMonths(1)->subDays(3)->toDateString(),
            'moyen' => 'Virement',
            'reference' => 'VIR-2026-0412',
            'created_by' => $admin->id,
        ]);

        // Échéance dépassée et rien d'encaissé : alimente « Montant en retard »
        // et le tour de contrôle des relances.
        Invoice::create([
            'finance_line_id' => $principale->id,
            'numero' => 'FAC-2026-002',
            'statut' => InvoiceStatut::Emise->value,
            'destinataire' => $destinataire($principale),
            'montant' => round($attendu * 0.7, 2),
            'date_emission' => now()->subMonths(1)->toDateString(),
            'date_echeance' => now()->subDays(12)->toDateString(),
            'commentaire' => 'Solde du financement — échéance dépassée.',
            'created_by' => $admin->id,
        ]);

        // 2) Autre ligne : facture réglée à moitié (statut « Partiel »).
        if ($autre = $lignes->skip(1)->first()) {
            $moitie = round((float) $autre->montant_attendu * 0.5, 2);

            $partielle = Invoice::create([
                'finance_line_id' => $autre->id,
                'numero' => 'FAC-2026-003',
                'statut' => InvoiceStatut::Emise->value,
                'destinataire' => $destinataire($autre),
                'montant' => $moitie,
                'date_emission' => now()->subDays(20)->toDateString(),
                'date_echeance' => now()->addDays(10)->toDateString(),
                'commentaire' => 'Premier appel de fonds.',
                'created_by' => $admin->id,
            ]);
            FinancePayment::create([
                'finance_line_id' => $autre->id,
                'invoice_id' => $partielle->id,
                'montant' => round($moitie * 0.4, 2),
                'date_paiement' => now()->subDays(5)->toDateString(),
                'moyen' => 'Virement',
                'reference' => 'VIR-2026-0587',
                'created_by' => $admin->id,
            ]);
        }
    }

    private function user(string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password')]);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * Dote un candidat de ses pièces obligatoires de candidature (pièce
     * d'identité, CV, carte vitale) sous forme de documents GED typés — comme
     * le fait le formulaire public. Reflète la réalité : un candidat existant
     * n'a jamais de pièce obligatoire manquante.
     */
    private function doterPiecesCandidature(Candidate $candidate): void
    {
        foreach (Candidate::piecesAttendues() as $type) {
            $document = $candidate->documents()->firstOrCreate(
                ['type' => $type->value],
                [
                    'statut' => DocumentStatut::Recu->value,
                    'source' => DocumentSource::Candidature->value,
                    'nom_fichier' => $type->getLabel(),
                ],
            );

            // Un fichier réel, sinon la pièce ment : elle s'annonce « reçue » et
            // ne s'ouvre pas. La GED entière était creuse — 183 documents, aucun
            // fichier — et l'utilisateur cliquait dans le vide.
            if ($document->getFirstMedia('fichier') === null) {
                $document->addMediaFromString(self::pdfDemo())
                    ->usingFileName(Str::slug($type->getLabel()).'_'.$candidate->id.'.pdf')
                    ->toMediaCollection('fichier');
            }
        }
    }

    /** PDF minimal valide — défini une seule fois, dans la factory. */
    private static function pdfDemo(): string
    {
        return CandidateFactory::pdfDemo();
    }

    /** Attache un CV de démonstration (PDF minimal valide) au candidat. */
    private function attacherCvDemo(Candidate $candidate): void
    {
        if ($candidate->getFirstMedia('cv') !== null) {
            return;
        }

        // PDF minimal valide (détecté comme application/pdf, accepté par la collection).
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 200]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";

        $candidate->addMediaFromString($pdf)
            ->usingFileName('CV_'.str_replace(' ', '_', $candidate->nom_complet).'.pdf')
            ->toMediaCollection('cv');
    }
}
