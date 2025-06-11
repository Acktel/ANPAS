<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge il campo 'indirizzo' alla tabella 'associazioni'
     */
    public function up(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->string('indirizzo', 255)->nullable()->after('citta');
        });
    }

    /**
     * Rimuove il campo 'indirizzo' (rollback)
     */
    public function down(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->dropColumn('indirizzo');
        });
    }
};
