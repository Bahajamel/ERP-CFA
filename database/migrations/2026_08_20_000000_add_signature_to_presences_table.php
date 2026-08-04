<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signature électronique de l'apprenant sur l'émargement (V2) : chaque présence
 * porte un jeton de signature (lien personnel envoyé à l'apprenant), l'horodatage
 * et l'IP de la signature — preuve réelle de présence à la séance. L'image de la
 * signature est stockée via la collection média « signature » (disque privé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table): void {
            $table->string('signature_token', 64)->nullable()->unique()->after('commentaire');
            $table->timestamp('signed_at')->nullable()->after('signature_token');
            $table->string('signed_ip', 45)->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table): void {
            $table->dropColumn(['signature_token', 'signed_at', 'signed_ip']);
        });
    }
};
