<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aggiunge il campo AnnoAcquisto agli automezzi
     * (in caso di acquisto mezzi non di prima immatricolazione)
     */
    public function up(): void {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->year('AnnoAcquisto')
                  ->nullable()
                  ->after('AnnoPrimaImmatricolazione')
                  ->comment("In caso di acquisto mezzi non di prima immatricolazione");
        });
    }

    /**
     * Ripristina la struttura rimuovendo AnnoAcquisto.
     */
    public function down(): void {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn('AnnoAcquisto');
        });
    }
};
