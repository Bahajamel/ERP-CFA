<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retrait de la fonctionnalité « service fait » : la table (et les éventuelles
 * preuves PDF en GED) disparaissent. La migration de création a été supprimée —
 * ce drop couvre les bases déjà migrées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_faits');

        if (Schema::hasTable('documents')) {
            DB::table('documents')->where('type', 'preuve_service_fait')->delete();
        }
    }

    public function down(): void
    {
        // Fonctionnalité supprimée : pas de retour arrière.
    }
};
