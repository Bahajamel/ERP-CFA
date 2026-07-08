<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte du cycle apprenant : Candidat → Matching → Contrat → OPCO →
 * Admission officielle → Rupture.
 *
 * Schéma (additif) :
 *  - admissions.contract_id (unique, nullable) : une admission officielle
 *    par contrat ; l'unicité par candidat est levée (un candidat peut
 *    connaître plusieurs contrats dans le temps).
 *  - matchings.origine : « cfa » (proposé par le CFA) ou « candidat »
 *    (entreprise trouvée par le candidat).
 *
 * Données (migration douce, one-way) :
 *  - statuts candidats/matchings/ruptures remappés vers les nouveaux enums ;
 *  - les admissions sont reliées au contrat signé du candidat ; les
 *    dossiers de pré-admission sans contrat signé (anciens artefacts créés
 *    automatiquement à la création du candidat) sont retirés — l'admission
 *    représente désormais l'admission officielle, pas la pré-admission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropUnique(['candidate_id']);
            $table->index('candidate_id');
            $table->foreignId('contract_id')->nullable()->unique()
                ->constrained('contracts')->nullOnDelete();
        });

        Schema::table('matchings', function (Blueprint $table) {
            $table->string('origine')->default('cfa')->after('candidate_id');
        });

        // Nouveaux statuts par défaut (premier état de chaque module).
        Schema::table('candidates', fn (Blueprint $t) => $t->string('statut')->default('entretien_prevu')->change());
        Schema::table('matchings', fn (Blueprint $t) => $t->string('statut')->default('en_recherche')->change());
        Schema::table('ruptures', fn (Blueprint $t) => $t->string('statut')->default('a_traiter')->change());

        $this->migrerDonnees();
    }

    private function migrerDonnees(): void
    {
        // Candidats : dossier en cours → entretien prévu ; parcours engagé → accepté.
        DB::table('candidates')->whereIn('statut', ['incomplet', 'complet'])->update(['statut' => 'entretien_prevu']);
        DB::table('candidates')->whereIn('statut', ['en_recherche_entreprise', 'contrat_signe', 'rupture'])->update(['statut' => 'accepte']);

        // Matchings : remap vers les six statuts du nouveau workflow.
        foreach ([
            'propose' => 'en_recherche',
            'cv_envoye' => 'proposition_envoyee',
            'attente_retour' => 'proposition_envoyee',
            'entretien_prevu' => 'entretien_entreprise',
            'refuse_entreprise' => 'refuse',
            'refuse_candidat' => 'refuse',
        ] as $ancien => $nouveau) {
            DB::table('matchings')->where('statut', $ancien)->update(['statut' => $nouveau]);
        }

        // Ruptures : tout dossier non clôturé revient « à traiter ».
        DB::table('ruptures')
            ->whereIn('statut', ['ouverte', 'en_accompagnement', 'recherche_employeur', 'reclasse'])
            ->update(['statut' => 'a_traiter']);

        // Admissions : rattacher chaque dossier au contrat signé du candidat.
        $signes = ['signe', 'transmis_opco', 'actif', 'rompu'];

        foreach (DB::table('admissions')->orderBy('id')->get() as $admission) {
            $contrat = DB::table('contracts')
                ->where('candidate_id', $admission->candidate_id)
                ->whereIn('statut_contrat', $signes)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            if ($contrat === null) {
                // Ancien artefact de pré-admission : un refus de pré-admission
                // redevient une décision candidat, puis le dossier est retiré.
                if ($admission->statut === 'refuse') {
                    DB::table('candidates')->where('id', $admission->candidate_id)->update(['statut' => 'refuse']);
                }

                DB::table('admission_checklist_items')->where('admission_id', $admission->id)->delete();
                DB::table('admissions')->where('id', $admission->id)->delete();

                continue;
            }

            DB::table('admissions')->where('id', $admission->id)->update([
                'contract_id' => $contrat->id,
                'statut' => match (true) {
                    $contrat->statut_contrat === 'rompu' => 'rupture',
                    $admission->statut === 'valide' => 'valide',
                    default => 'a_verifier',
                },
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropIndex(['candidate_id']);
            $table->unique('candidate_id');
        });

        Schema::table('matchings', fn (Blueprint $t) => $t->dropColumn('origine'));

        Schema::table('candidates', fn (Blueprint $t) => $t->string('statut')->default('incomplet')->change());
        Schema::table('matchings', fn (Blueprint $t) => $t->string('statut')->default('propose')->change());
        Schema::table('ruptures', fn (Blueprint $t) => $t->string('statut')->default('ouverte')->change());

        // Le remappage des statuts et le retrait des pré-admissions sont one-way.
    }
};
