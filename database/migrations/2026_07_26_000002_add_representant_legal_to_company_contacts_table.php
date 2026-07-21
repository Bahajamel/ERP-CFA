<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque le contact « représentant légal » de l'entreprise (signataire de la
 * convention et du contrat côté employeur), distinct du contact opérationnel
 * (is_principal) et du tuteur / maître d'apprentissage (is_tuteur). Le poste
 * occupé est stocké dans le champ `fonction` existant.
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_contacts', function (Blueprint $table): void {
            $table->boolean('is_representant_legal')->default(false)->after('is_tuteur');
        });
    }

    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table): void {
            $table->dropColumn('is_representant_legal');
        });
    }
};
