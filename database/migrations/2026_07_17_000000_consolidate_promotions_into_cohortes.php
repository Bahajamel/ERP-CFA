<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recentrage du modèle : une classe (Promotion) = une COHORTE (formation +
 * année), la matière vit au niveau de la séance. Les classes dupliquées par
 * matière (ex. « CDA 1ère année — Dév web » et « … — Anglais ») sont
 * fusionnées : séances et apprenants repointés vers la cohorte conservée,
 * la matière de la classe recopiée sur ses séances, puis la colonne disparaît.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Les séances sans matière héritent de celle de leur classe.
        DB::table('seances')->whereNull('libelle')->orderBy('id')->each(function ($seance): void {
            $matiere = DB::table('promotions')->where('id', $seance->promotion_id)->value('matiere');

            if ($matiere !== null) {
                DB::table('seances')->where('id', $seance->id)->update(['libelle' => $matiere]);
            }
        });

        // 2) Fusion des doublons de cohorte (même formation + libellé + année scolaire).
        $groupes = DB::table('promotions')
            ->selectRaw('MIN(id) as garde, formation_id, libelle, annee_scolaire')
            ->groupBy('formation_id', 'libelle', 'annee_scolaire')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groupes as $groupe) {
            $doublons = DB::table('promotions')
                ->where('formation_id', $groupe->formation_id)
                ->where('libelle', $groupe->libelle)
                ->where('annee_scolaire', $groupe->annee_scolaire)
                ->where('id', '!=', $groupe->garde)
                ->pluck('id');

            foreach ($doublons as $doublon) {
                DB::table('seances')->where('promotion_id', $doublon)->update(['promotion_id' => $groupe->garde]);

                // Apprenants : repointés sauf s'ils sont déjà dans la cohorte conservée.
                DB::table('candidate_promotion')->where('promotion_id', $doublon)->orderBy('id')
                    ->each(function ($ligne) use ($groupe): void {
                        $dejaMembre = DB::table('candidate_promotion')
                            ->where('promotion_id', $groupe->garde)
                            ->where('candidate_id', $ligne->candidate_id)
                            ->exists();

                        $dejaMembre
                            ? DB::table('candidate_promotion')->where('id', $ligne->id)->delete()
                            : DB::table('candidate_promotion')->where('id', $ligne->id)->update(['promotion_id' => $groupe->garde]);
                    });

                DB::table('promotions')->where('id', $doublon)->delete();
            }
        }

        // 3) La matière n'est plus un attribut de la classe.
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('matiere');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('matiere')->nullable()->after('libelle');
        });
        // La fusion des doublons n'est pas réversible.
    }
};
