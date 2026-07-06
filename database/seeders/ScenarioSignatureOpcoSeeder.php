<?php

namespace Database\Seeders;

use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Services\SignatureService;
use Illuminate\Database\Seeder;

/**
 * Scénario de test « signature → dossier OPCO ».
 *
 * Crée un contrat PRÊT À SIGNER mais SANS dossier OPCO, avec une demande de
 * signature électronique en cours. Objectif : ouvrir le contrat, cliquer
 * « Simuler la signature (démo) » et vérifier que le dossier OPCO s'ouvre
 * automatiquement. Le contrat est volontairement laissé en amont (« Prêt à
 * vérifier ») pour tester le chemin qui échouait auparavant.
 *
 * Idempotent : ne recrée rien si le candidat de test existe déjà.
 * Lancement : php artisan db:seed --class=ScenarioSignatureOpcoSeeder
 */
class ScenarioSignatureOpcoSeeder extends Seeder
{
    private const EMAIL_TEST = 'test.signature@cfa-demo.fr';

    public function run(): void
    {
        if (Candidate::where('email', self::EMAIL_TEST)->exists()) {
            $this->command?->warn('Scénario signature/OPCO déjà présent — ignoré.');

            return;
        }

        $formation = Formation::query()->whereNotNull('code_rncp')->first()
            ?? Formation::factory()->create();

        $company = Company::create([
            'raison_sociale' => 'DEMO Signature SARL',
            'siret' => '90000000000017',
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
            'statut_contrat' => ContractStatut::PretAVerifier,
            'statut_signature' => ContractSignatureStatut::NonSigne,
        ]);

        // Envoi en signature électronique (simulation) : crée une demande en cours,
        // sans ouvrir le dossier OPCO (il ne doit s'ouvrir qu'à la signature finale).
        app(SignatureService::class)->envoyer($contract);

        $this->command?->info(
            "Scénario prêt : contrat #{$contract->id} — DEMO Signature SARL / Durand Test-Signature. "
            .'Ouvrez-le, cliquez « Simuler la signature (démo) », puis vérifiez le dossier OPCO.'
        );
    }
}
