<?php

namespace Database\Seeders;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ChecklistItemStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
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
use App\Models\Formation;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        $candidatesData = [
            ['Lucas', 'Petit', $devWeb, CandidateStatut::ContratSigne, 'Bac+2'],
            ['Emma', 'Roux', $cyber, CandidateStatut::EnRechercheEntreprise, 'Bac'],
            ['Hugo', 'Fontaine', $commerce, CandidateStatut::Complet, 'Bac'],
            ['Léa', 'Girard', $marketing, CandidateStatut::EnRechercheEntreprise, 'Bac+2'],
            ['Nathan', 'Lambert', $compta, CandidateStatut::Incomplet, 'Bac'],
            ['Chloé', 'Mercier', $devWeb, CandidateStatut::Complet, 'Bac+2'],
            ['Maxime', 'Blanc', $cyber, CandidateStatut::EnRechercheEntreprise, 'Bac'],
            ['Sarah', 'Faure', $commerce, CandidateStatut::ContratSigne, 'Bac'],
            ['Théo', 'Garnier', $marketing, CandidateStatut::Incomplet, 'Bac+2'],
            ['Inès', 'Chevalier', $compta, CandidateStatut::Rupture, 'Bac'],
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

        // -------- Matching (propositions candidat ↔ besoin) --------
        Matching::create(['need_id' => $needs[0]->id, 'candidate_id' => $candidates[0]->id, 'statut' => MatchingStatut::Accepte, 'cv_envoye' => true, 'date_entretien' => now()->subDays(20), 'retour_entreprise' => 'Candidat retenu, profil parfait.', 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[1]->id, 'statut' => MatchingStatut::AttenteRetour, 'cv_envoye' => true, 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[6]->id, 'statut' => MatchingStatut::CvEnvoye, 'cv_envoye' => true, 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[2]->id, 'candidate_id' => $candidates[2]->id, 'statut' => MatchingStatut::EntretienPrevu, 'cv_envoye' => true, 'date_entretien' => now()->addDays(5), 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[3]->id, 'candidate_id' => $candidates[3]->id, 'statut' => MatchingStatut::Propose, 'cv_envoye' => false, 'assigned_by' => $sophie->id]);
        Matching::create(['need_id' => $needs[5]->id, 'candidate_id' => $candidates[7]->id, 'statut' => MatchingStatut::Accepte, 'cv_envoye' => true, 'date_entretien' => now()->subDays(30), 'retour_entreprise' => 'Embauché.', 'assigned_by' => $thomas->id]);
        Matching::create(['need_id' => $needs[1]->id, 'candidate_id' => $candidates[3]->id, 'statut' => MatchingStatut::RefuseEntreprise, 'cv_envoye' => true, 'retour_entreprise' => 'Profil non retenu.', 'assigned_by' => $thomas->id]);

        // Le besoin « Vendeur en boulangerie » a un candidat accepté : on le clôture
        // proprement en « Pourvu » (date de clôture + cascade des pistes ouvertes).
        $needs[5]->transitionTo(NeedStatut::Pourvu);

        // -------- Admissions + checklist --------
        // Candidat 0 : dossier validé (parcours complet)
        $adm0 = Admission::create(['candidate_id' => $candidates[0]->id, 'statut' => AdmissionStatut::Valide, 'validated_by' => $admission->id, 'validated_at' => now()->subDays(15), 'commentaire' => 'Dossier complet et conforme.']);
        $this->checklist($adm0, [
            [DocumentType::CvCandidat, ChecklistItemStatut::Presente],
            [DocumentType::TestPositionnement, ChecklistItemStatut::Presente],
            [DocumentType::Cerfa, ChecklistItemStatut::Presente],
            [DocumentType::Convention, ChecklistItemStatut::Presente],
        ]);
        // Candidat 2 : à vérifier, une pièce manquante
        $adm2 = Admission::create(['candidate_id' => $candidates[2]->id, 'statut' => AdmissionStatut::AVerifier]);
        $this->checklist($adm2, [
            [DocumentType::CvCandidat, ChecklistItemStatut::Presente],
            [DocumentType::TestPositionnement, ChecklistItemStatut::Presente],
            [DocumentType::Cerfa, ChecklistItemStatut::Manquante],
        ]);
        // Candidat 5 : incomplet
        $adm5 = Admission::create(['candidate_id' => $candidates[5]->id, 'statut' => AdmissionStatut::Incomplet]);
        $this->checklist($adm5, [
            [DocumentType::CvCandidat, ChecklistItemStatut::Presente],
            [DocumentType::TestPositionnement, ChecklistItemStatut::Manquante],
            [DocumentType::JustificatifAbsence, ChecklistItemStatut::NonConforme],
        ]);
        // Candidat 7 : validé
        $adm7 = Admission::create(['candidate_id' => $candidates[7]->id, 'statut' => AdmissionStatut::Valide, 'validated_by' => $admission->id, 'validated_at' => now()->subDays(25)]);
        $this->checklist($adm7, [
            [DocumentType::CvCandidat, ChecklistItemStatut::Presente],
            [DocumentType::Cerfa, ChecklistItemStatut::Presente],
            [DocumentType::Convention, ChecklistItemStatut::Presente],
        ]);

        // -------- Contrats --------
        // Contrat 0 : signé + transmis OPCO, dossier OPCO REJETÉ → en correction (bloqué)
        $contrat0 = Contract::create([
            'candidate_id' => $candidates[0]->id, 'company_id' => $companies[0]['model']->id, 'formation_id' => $devWeb->id,
            'code_rncp' => $devWeb->code_rncp, 'date_debut' => now()->addMonth()->startOfMonth(), 'date_fin' => now()->addMonths(19)->startOfMonth(),
            'tuteur_id' => $companies[0]['tuteur']->id, 'rythme' => $devWeb->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::TransmisOpco,
        ]);
        // Contrat 1 : signé + actif, OPCO accepté
        $contrat1 = Contract::create([
            'candidate_id' => $candidates[7]->id, 'company_id' => $companies[1]['model']->id, 'formation_id' => $commerce->id,
            'code_rncp' => $commerce->code_rncp, 'date_debut' => now()->subMonths(2)->startOfMonth(), 'date_fin' => now()->addMonths(22)->startOfMonth(),
            'tuteur_id' => $companies[1]['tuteur']->id, 'rythme' => $commerce->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Actif,
        ]);
        // Contrat 2 : en préparation (brouillon)
        $contrat2 = Contract::create([
            'candidate_id' => $candidates[2]->id, 'company_id' => $companies[3]['model']->id, 'formation_id' => $commerce->id,
            'code_rncp' => $commerce->code_rncp, 'date_debut' => now()->addMonths(2)->startOfMonth(),
            'tuteur_id' => $companies[3]['tuteur']->id, 'rythme' => $commerce->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::NonSigne, 'statut_contrat' => ContractStatut::Brouillon,
        ]);
        // Contrat 3 : envoyé pour signature
        $contrat3 = Contract::create([
            'candidate_id' => $candidates[5]->id, 'company_id' => $companies[0]['model']->id, 'formation_id' => $devWeb->id,
            'code_rncp' => $devWeb->code_rncp, 'date_debut' => now()->addMonths(2)->startOfMonth(),
            'tuteur_id' => $companies[0]['tuteur']->id, 'rythme' => $devWeb->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Envoye, 'statut_contrat' => ContractStatut::EnvoyeSignature,
        ]);

        // -------- Dossiers OPCO --------
        // Bloqué : rejeté puis en correction
        OpcoFile::create([
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
        // Déposé, en attente de retour
        OpcoFile::create([
            'contract_id' => $contrat3->id, 'opco_id' => $opcoAtlas->id, 'date_depot' => now()->subDays(4),
            'statut' => OpcoStatut::Depose, 'montant_prevu' => 9200,
        ]);

        // -------- Tâches & alertes --------
        $tasksData = [
            ['Relancer l\'entreprise Webtech pour la signature', $sophie->id, TaskPriorite::Haute, TaskStatut::AFaire, now()->addDays(2), $contrat3],
            ['Corriger le CERFA (NIR erroné) – dossier OPCO Atlas', $administratif->id, TaskPriorite::Urgente, TaskStatut::EnCours, now()->subDay(), $contrat0],
            ['Compléter la pièce manquante (CERFA) – dossier Hugo Fontaine', $admission->id, TaskPriorite::Normale, TaskStatut::AFaire, now()->addDays(3), $candidates[2]],
            ['Appeler Nathan Lambert – dossier incomplet', $thomas->id, TaskPriorite::Normale, TaskStatut::EnRetard, now()->subDays(2), $candidates[4]],
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
        FinanceLine::create([
            'contract_id' => $contrat1->id,
            'libelle' => 'Financement OPCO — année 1',
            'montant_attendu' => 8000, 'montant_accepte' => 8000,
        ]);
        FinanceLine::create([
            'contract_id' => $contrat0->id,
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
        Rupture::create([
            'contract_id' => $contratRompu->id,
            'date_rupture' => now()->subDays(18),
            'motif' => RuptureMotif::Licenciement->value,
            'initiative' => 'Employeur',
            'statut' => RuptureStatut::EnAccompagnement->value,
            'accompagnement' => now()->subDays(15)->format('d/m/Y').' — Entretien réalisé, 2 pistes de reclassement identifiées.',
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
            'commercial_id' => $thomas->id, 'statut' => CandidateStatut::ContratSigne,
        ]);
        $contratRisque = Contract::create([
            'candidate_id' => $yanis->id, 'company_id' => $companies[2]['model']->id, 'formation_id' => $cyber->id,
            'code_rncp' => $cyber->code_rncp, 'date_debut' => now()->subMonths(3)->startOfMonth(), 'date_fin' => now()->addMonths(21)->startOfMonth(),
            'tuteur_id' => null, 'rythme' => $cyber->rythme_defaut, 'lieu_formation' => 'CFA - Site principal',
            'statut_signature' => ContractSignatureStatut::Signe, 'statut_contrat' => ContractStatut::Actif,
        ]);
        OpcoFile::create([
            'contract_id' => $contratRisque->id, 'opco_id' => $opco2i->id, 'date_depot' => now()->subMonths(2),
            'statut' => OpcoStatut::Rejete, 'montant_prevu' => 8600, 'motif_rejet' => 'Pièces justificatives incomplètes.',
        ]);
    }

    private function user(string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password')]);
        $user->syncRoles($role);

        return $user;
    }

    private function checklist(Admission $admission, array $items): void
    {
        foreach ($items as [$type, $statut]) {
            $admission->items()->create([
                'document_type' => $type,
                'est_obligatoire' => true,
                'statut' => $statut,
            ]);
        }
    }
}
