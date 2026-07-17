<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusionne l'identité du CFA (cfa_profiles) dans organisations.
 *
 * cfa_profiles était un SINGLETON sans organisation_id : CfaProfile::current()
 * renvoyait la première ligne de la table, quel que soit le CFA connecté. Deux
 * CFA sur la plateforme, et le second générait ses CERFA avec le SIRET, le
 * représentant légal et l'image de signature du premier — un document déposé à
 * l'OPCO et à l'État.
 *
 * Deux tables portaient aussi chacune un « nom du CFA » : organisations.nom
 * (sélecteur de tenant) et cfa_profiles.nom (documents). Ils pouvaient diverger
 * en silence — la Fiche du CFA annonçait « Apparaît […] sur vos documents » sous
 * celui qui n'y figurait justement pas.
 *
 * Un CFA = un enregistrement : le cloisonnement devient structurel plutôt que
 * dépendant d'un filtre qu'on peut oublier.
 */
return new class extends Migration
{
    /** Colonnes reprises telles quelles depuis cfa_profiles (hors `nom`, déjà porté par organisations). */
    private const COLONNES = [
        'raison_sociale', 'siren', 'siret', 'naf', 'nda', 'numero_uai',
        'adresse', 'code_postal', 'ville', 'telephone', 'email', 'website',
        'representant_nom', 'representant_prenom', 'representant_fonction',
        'referent_pedagogique_nom', 'referent_pedagogique_prenom',
        'referent_handicap_nom', 'referent_handicap_prenom',
        'referent_mobilite_nom', 'referent_mobilite_prenom',
        'dpo_nom', 'dpo_prenom',
    ];

    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            foreach (self::COLONNES as $colonne) {
                $table->string($colonne)->nullable();
            }

            $table->string('theme_defaut')->default('institutionnel');
            $table->string('format_defaut')->default('pdf');
            $table->boolean('verifier_rncp')->default(false);
        });

        if (! Schema::hasTable('cfa_profiles')) {
            return;
        }

        // Reprise des données : le profil unique existant devient l'identité du
        // CFA par défaut (le plus ancien — le CFA « maison » en mono-CFA).
        $profil = DB::table('cfa_profiles')->orderBy('id')->first();
        $organisation = DB::table('organisations')->orderBy('id')->first();

        if ($profil !== null && $organisation !== null) {
            $valeurs = [];

            foreach ([...self::COLONNES, 'theme_defaut', 'format_defaut', 'verifier_rncp'] as $colonne) {
                if (isset($profil->{$colonne})) {
                    $valeurs[$colonne] = $profil->{$colonne};
                }
            }

            // Le nom des documents (cfa_profiles.nom) fait foi : c'est celui que
            // les CERFA et conventions déjà émis portent.
            if (filled($profil->nom ?? null)) {
                $valeurs['nom'] = $profil->nom;
            }

            DB::table('organisations')->where('id', $organisation->id)->update($valeurs);

            // Logo, signature et cachet suivent leur propriétaire.
            DB::table('media')
                ->where('model_type', 'App\\Models\\CfaProfile')
                ->update([
                    'model_type' => 'App\\Models\\Organisation',
                    'model_id' => $organisation->id,
                ]);
        }

        Schema::drop('cfa_profiles');
    }

    public function down(): void
    {
        Schema::create('cfa_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nom')->nullable();

            foreach (self::COLONNES as $colonne) {
                $table->string($colonne)->nullable();
            }

            $table->string('theme_defaut')->default('institutionnel');
            $table->string('format_defaut')->default('pdf');
            $table->boolean('verifier_rncp')->default(false);
            $table->timestamps();
        });

        $organisation = DB::table('organisations')->orderBy('id')->first();

        if ($organisation !== null) {
            $valeurs = ['nom' => $organisation->nom, 'created_at' => now(), 'updated_at' => now()];

            foreach ([...self::COLONNES, 'theme_defaut', 'format_defaut', 'verifier_rncp'] as $colonne) {
                $valeurs[$colonne] = $organisation->{$colonne} ?? null;
            }

            $id = DB::table('cfa_profiles')->insertGetId($valeurs);

            DB::table('media')
                ->where('model_type', 'App\\Models\\Organisation')
                ->whereIn('collection_name', ['logo', 'signature', 'cachet'])
                ->update(['model_type' => 'App\\Models\\CfaProfile', 'model_id' => $id]);
        }

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn([...self::COLONNES, 'theme_defaut', 'format_defaut', 'verifier_rncp']);
        });
    }
};
