<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('code_rncp')->nullable();
            $table->string('niveau')->nullable();
            $table->unsignedSmallInteger('duree_mois')->nullable();
            $table->string('rythme_defaut')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('opcos', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
        Schema::dropIfExists('opcos');
    }
};
