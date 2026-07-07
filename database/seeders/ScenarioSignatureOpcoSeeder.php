<?php

namespace Database\Seeders;

use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use Illuminate\Database\Seeder;

/**
 * Scénario de test « signature → dossier OPCO ».
 *
 * Crée un contrat PRÊT À SIGNER (statut « Envoyé pour signature », CERFA joint)
 * mais SANS dossier OPCO. Objectif : ouvrir le contrat, cliquer « Marquer signé »
 * et vérifier que le dossier OPCO s'ouvre automatiquement.
 *
 * Re-lançable : purge d'abord ses propres données de test.
 * Lancement : php artisan db:seed --class=ScenarioSignatureOpcoSeeder
 */
class ScenarioSignatureOpcoSeeder extends Seeder
{
    private const EMAIL_TEST = 'test.signature@cfa-demo.fr';

    private const SIRET_TEST = '90000000000017';

    public function run(): void
    {
        $this->purgerAncienScenario();

        $formation = Formation::query()->whereNotNull('code_rncp')->first()
            ?? Formation::factory()->create();

        $company = Company::create([
            'raison_sociale' => 'DEMO Signature SARL',
            'siret' => self::SIRET_TEST,
            'secteur' => 'Informatique',
            'statut' => CompanyStatut::Partenaire,
        ]);

        $tuteur = CompanyContact::create([
            'company_id' => $company->id,
            'nom' => 'Martin',
            'prenom' => 'Claire',
            'email' => 'tuteur.demo@entreprise.fr',
            'telephone' => '0611111111',
            'is_principal' => true,
            'is_tuteur' => true,
        ]);

        $candidate = Candidate::create([
            'nom' => 'Durand',
            'prenom' => 'Test-Signature',
            'email' => self::EMAIL_TEST,
            'telephone' => '0600000000',
            'date_naissance' => now()->subYears(22),
            'formation_visee_id' => $formation->id,
            'statut' => CandidateStatut::Complet,
        ]);

        $contract = Contract::create([
            'candidate_id' => $candidate->id,
            'company_id' => $company->id,
            'formation_id' => $formation->id,
            'tuteur_id' => $tuteur->id,
            'code_rncp' => $formation->code_rncp ?? 'RNCP00000',
            'rythme' => '2 j CFA / 3 j entreprise',
            'date_debut' => now()->addWeeks(2)->startOfDay(),
            'date_fin' => now()->addWeeks(2)->addMonths(24)->startOfDay(),
            'lieu_formation' => 'CFA — Site principal',
            'statut_contrat' => ContractStatut::EnvoyeSignature,
            'statut_signature' => ContractSignatureStatut::NonSigne,
        ]);

        // CERFA rattaché : satisfait la garde « pas de Signé sans document contractuel ».
        $contract->documents()->create([
            'type' => DocumentType::Cerfa->value,
            'statut' => DocumentStatut::Recu->value,
            'nom_fichier' => 'CERFA (démo)',
        ]);

        $this->command?->info(
            "Scénario prêt : contrat #{$contract->id} — DEMO Signature SARL / Durand Test-Signature. "
            .'Ouvrez-le, cliquez « Marquer signé », puis vérifiez que le dossier OPCO s\'est ouvert.'
        );
    }

    /** Supprime les données du scénario précédent pour repartir propre. */
    private function purgerAncienScenario(): void
    {
        $candidate = Candidate::withTrashed()->where('email', self::EMAIL_TEST)->first();
        if ($candidate !== null) {
            $candidate->contracts()->withTrashed()->get()->each(function (Contract $c): void {
                $c->opcoFile()->delete();
                $c->documents()->delete();
                $c->forceDelete();
            });
            $candidate->forceDelete();
        }

        Company::withTrashed()->where('siret', self::SIRET_TEST)->get()
            ->each(fn (Company $c) => $c->forceDelete());
    }
}
