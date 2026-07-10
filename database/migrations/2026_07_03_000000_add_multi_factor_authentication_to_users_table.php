<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentification multi-facteurs (MFA / 2FA) — story P0-01-2.
 *
 * Deux colonnes chiffrées portées par l'utilisateur :
 *  - le secret TOTP partagé avec son application d'authentification ;
 *  - ses codes de secours (hachés) pour récupérer l'accès en cas de perte du téléphone.
 * Nullable : la MFA reste facultative tant que l'utilisateur ne l'active pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
