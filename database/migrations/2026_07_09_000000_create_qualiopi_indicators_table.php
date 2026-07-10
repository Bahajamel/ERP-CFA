<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre Qualiopi : un enregistrement par indicateur du RNQ, portant à la fois
 * la donnée de référence (numéro, critère, libellé) et l'état de conformité de
 * l'organisme (statut, responsable, preuves via la GED). Mono-organisme (un CFA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualiopi_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('numero')->unique();
            $table->unsignedTinyInteger('critere')->index();
            $table->text('libelle');
            $table->boolean('specifique_cfa')->default(false);
            $table->string('statut')->default('a_verifier')->index();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualiopi_indicators');
    }
};
