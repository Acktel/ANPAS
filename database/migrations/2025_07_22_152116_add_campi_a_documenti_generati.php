<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('documenti_generati', function (Blueprint $table) {
            $table->string('nome_file')->nullable();
            $table->string('percorso_file')->nullable();
        });
    }

    public function down(): void {
        Schema::table('documenti_generati', function (Blueprint $table) {
            $table->dropColumn(['nome_file', 'percorso_file']);
        });
    }
};
