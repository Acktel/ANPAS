<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aziende_sanitarie', function (Blueprint $table) {
            // nuovi campi
            $table->string('indirizzo_via', 255)->nullable()->after('Indirizzo');
            $table->string('indirizzo_civico', 50)->nullable()->after('indirizzo_via');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aziende_sanitarie', function (Blueprint $table) {
            $table->dropColumn(['indirizzo_via', 'indirizzo_civico']);
        });
    }
};
