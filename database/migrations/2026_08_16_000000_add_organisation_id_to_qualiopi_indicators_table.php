<?php

use App\Models\Organisation;
use App\Qualiopi\ReferentielQualiopi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cloisonne l'état de conformité Qualiopi par CFA.
 *
 * Jusqu'ici les 32 indicateurs étaient une table de référence GLOBALE : leur
 * statut de conformité était donc partagé entre tous les CFA — inacceptable dès
 * le 2e client. On rattache chaque ligne à une organisation ; chaque CFA a ses
 * 32 indicateurs et son propre état.
 *
 * Reprise des données existantes : les lignes actuelles sont attribuées au CFA
 * par défaut (leur état de conformité déjà saisi lui revient), puis les 32
 * indicateurs sont provisionnés pour chaque autre CFA existant (état vierge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualiopi_indicators', function (Blueprint $table): void {
            $table->foreignId('organisation_id')->nullable()->after('id')
                ->constrained('organisations')->nullOnDelete();
        });

        // L'unicité passe de « numero » à « (organisation_id, numero) ».
        Schema::table('qualiopi_indicators', function (Blueprint $table): void {
            $table->dropUnique('qualiopi_indicators_numero_unique');
        });

        // Reprise des données (bases existantes uniquement ; no-op à l'install).
        $defaut = Organisation::query()->where('actif', true)->orderBy('id')->first()
            ?? Organisation::query()->orderBy('id')->first();

        if ($defaut !== null) {
            // Les lignes historiques (sans CFA) reviennent au CFA par défaut.
            DB::table('qualiopi_indicators')->whereNull('organisation_id')->update([
                'organisation_id' => $defaut->id,
            ]);
        }

        Schema::table('qualiopi_indicators', function (Blueprint $table): void {
            $table->unique(['organisation_id', 'numero']);
        });

        // Chaque autre CFA reçoit ses 32 indicateurs (état vierge).
        if ($defaut !== null) {
            Organisation::query()->whereKeyNot($defaut->id)->each(
                fn (Organisation $organisation) => ReferentielQualiopi::provisionner($organisation->id),
            );
        }
    }

    public function down(): void
    {
        Schema::table('qualiopi_indicators', function (Blueprint $table): void {
            $table->dropUnique(['organisation_id', 'numero']);
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
