<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            // Date de clôture du besoin : renseignée automatiquement lorsque le
            // besoin est pourvu ou annulé (auto-clôture, P0-04-3).
            $table->date('date_cloture')->nullable()->after('date_demarrage');
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->dropColumn('date_cloture');
        });
    }
};
