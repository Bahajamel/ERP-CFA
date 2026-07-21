<?php

namespace Database\Seeders;

use App\Enums\CompanyStatut;
use App\Enums\NeedOrigine;
use App\Enums\NeedStatut;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

/**
 * Jeu d'essai de la fiche besoin publique : cinq entreprises « Prospect » ayant
 * chacune déposé un besoin via /entreprise, en attente de validation commerciale.
 *
 * Sert à voir l'écran peuplé (bloc « N besoins à valider », badge « À valider »,
 * actions Valider / Rejeter) sans avoir à remplir le formulaire cinq fois.
 *
 * Rejouable : les entreprises sont identifiées par leur SIRET, les besoins par
 * leur intitulé. Tout est préfixé « [démo] » pour être retrouvé et supprimé —
 * cf. la commande de nettoyage en fin de fichier.
 *
 *   php artisan db:seed --class=DemoFicheBesoinSeeder
 */
class DemoFicheBesoinSeeder extends Seeder
{
    /** Préfixe de repérage : tout ce que ce seeder crée en porte la marque. */
    public const MARQUEUR = '[démo] ';

    /**
     * SIRET volontairement fictifs (14 chiffres, format valide) : le formulaire
     * les accepte, mais aucun ne correspond à une vraie entreprise. Rien de réel
     * n'atterrit donc dans la base de démonstration.
     */
    private const DOSSIERS = [
        [
            'siret' => '90000000100011',
            'raison_sociale' => 'Boulangerie Lecoq',
            'secteur' => 'Boulangerie et boulangerie-pâtisserie',
            'adresse' => '12 rue du Four, 69003 Lyon',
            'contact' => ['Lecoq', 'Sylvie', 'sylvie.lecoq@example.test', '+33478000001', 'Gérante'],
            'poste' => 'Apprenti boulanger',
            'formation' => null,
            'postes' => 2,
            'demarrage' => '+2 months',
            'rythme' => '1 semaine CFA / 3 semaines entreprise',
            'prerequis' => 'Lever tôt (prise de poste 5h), goût du travail en équipe. Aucune expérience exigée : nous formons sur place.',
        ],
        [
            'siret' => '90000000200012',
            'raison_sociale' => 'Novatek Solutions',
            'secteur' => 'Programmation informatique',
            'adresse' => '8 avenue Jean Jaurès, 69007 Lyon',
            'contact' => ['Benali', 'Karim', 'k.benali@example.test', '+33472000002', 'Directeur technique'],
            'poste' => 'Développeur web junior',
            'formation' => 'Titre Professionnel Développeur Web et Web Mobile',
            'postes' => 1,
            'demarrage' => '+3 months',
            'rythme' => '2 j CFA / 3 j entreprise',
            'prerequis' => 'Bases en PHP et JavaScript. Git apprécié. Travail sur une application métier interne, en binôme avec un développeur confirmé.',
        ],
        [
            'siret' => '90000000300013',
            'raison_sociale' => 'Cabinet Morel & Associés',
            'secteur' => 'Activités comptables',
            'adresse' => '45 cours Lafayette, 69006 Lyon',
            'contact' => ['Morel', 'Anne', 'a.morel@example.test', '+33478000003', 'Expert-comptable'],
            'poste' => 'Assistant comptable en alternance',
            'formation' => 'Gestionnaire Comptable et Fiscal',
            'postes' => 2,
            'demarrage' => '+1 month',
            'rythme' => '2 j CFA / 3 j entreprise',
            'prerequis' => 'Rigueur et discrétion. Saisie, rapprochements bancaires, préparation des déclarations de TVA.',
        ],
        [
            'siret' => '90000000400014',
            'raison_sociale' => 'Maison Dubreuil',
            'secteur' => 'Commerce de détail d\'habillement',
            'adresse' => '3 place Bellecour, 69002 Lyon',
            'contact' => ['Dubreuil', 'Thomas', 't.dubreuil@example.test', '+33478000004', 'Responsable de magasin'],
            'poste' => 'Vendeur conseil',
            'formation' => 'Négociation et Digitalisation de la Relation Client',
            'postes' => 3,
            'demarrage' => '+2 months',
            'rythme' => '1 j CFA / 4 j entreprise',
            'prerequis' => 'Présentation soignée, aisance à l\'oral. Travail le samedi. Formation assurée sur nos produits.',
        ],
        [
            // Volontairement minimal : reproduit une entreprise pressée qui remplit
            // le strict nécessaire — le commercial devra la rappeler pour qualifier.
            'siret' => '90000000500015',
            'raison_sociale' => 'Transports Vidal',
            'secteur' => null,
            'adresse' => 'Zone industrielle des Chênes, 69800 Saint-Priest',
            'contact' => ['Vidal', null, 'contact@example.test', null, null],
            'poste' => 'Assistant de gestion',
            'formation' => null,
            'postes' => 1,
            'demarrage' => null,
            'rythme' => null,
            'prerequis' => null,
        ],
    ];

    public function run(): void
    {
        $organisation = Organisation::defaut();

        if ($organisation === null) {
            $this->command?->warn('Aucun CFA actif : rien à faire.');

            return;
        }

        foreach (self::DOSSIERS as $dossier) {
            $company = Company::withoutGlobalScopes()->updateOrCreate(
                ['siret' => $dossier['siret']],
                [
                    'organisation_id' => $organisation->id,
                    'raison_sociale' => self::MARQUEUR.$dossier['raison_sociale'],
                    'secteur' => $dossier['secteur'],
                    'adresse' => $dossier['adresse'],
                    'statut' => CompanyStatut::Prospect,
                ],
            );

            [$nom, $prenom, $email, $telephone, $fonction] = $dossier['contact'];

            $contact = $company->contacts()->updateOrCreate(
                ['email' => $email],
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'telephone' => $telephone,
                    'fonction' => $fonction,
                    'is_principal' => true,
                ],
            );

            Need::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'intitule_poste' => self::MARQUEUR.$dossier['poste'],
                ],
                [
                    'organisation_id' => $organisation->id,
                    'contact_id' => $contact->id,
                    'formation_id' => $this->formation($organisation, $dossier['formation']),
                    'nb_postes' => $dossier['postes'],
                    'date_demarrage' => $dossier['demarrage'] ? now()->modify($dossier['demarrage'])->toDateString() : null,
                    'rythme' => $dossier['rythme'],
                    'localisation' => $dossier['adresse'],
                    'prerequis' => $dossier['prerequis'],
                    'statut' => NeedStatut::Cree,
                    // Le cœur du jeu d'essai : déposés par l'entreprise, non relus.
                    'origine' => NeedOrigine::Entreprise,
                    'validee_at' => null,
                ],
            );
        }

        $this->command?->info(count(self::DOSSIERS).' besoins en attente de validation créés.');
        $this->command?->line('Pour tout retirer : php artisan demo:fiche-besoin --nettoyer');
    }

    /** Formation du CFA portant ce libellé, si elle existe (sinon : non renseignée). */
    private function formation(Organisation $organisation, ?string $libelle): ?int
    {
        if ($libelle === null) {
            return null;
        }

        return Formation::withoutGlobalScopes()
            ->where('organisation_id', $organisation->id)
            ->where('libelle', $libelle)
            ->value('id');
    }

    /**
     * Retire tout ce que ce seeder a créé (repéré par le préfixe « [démo] »).
     *
     *   php artisan tinker --execute="(new Database\Seeders\DemoFicheBesoinSeeder)->nettoyer();"
     */
    public function nettoyer(): void
    {
        $entreprises = Company::withoutGlobalScopes()
            ->where('raison_sociale', 'like', self::MARQUEUR.'%')
            ->get();

        foreach ($entreprises as $entreprise) {
            Need::withoutGlobalScopes()->where('company_id', $entreprise->id)->delete();
            $entreprise->contacts()->delete();
            $entreprise->forceDelete();
        }

        $this->command?->info($entreprises->count().' entreprises de démonstration supprimées.');
    }
}
