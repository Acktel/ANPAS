<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            // Modello passa da NOT NULL a NULL
            $table->string('Modello', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Se torni indietro, prima ripulisci i NULL altrimenti fallisce
        DB::table('automezzi')->whereNull('Modello')->update(['Modello' => '']);
        Schema::table('automezzi', function (Blueprint $table) {
            $table->string('Modello', 255)->nullable(false)->default('')->change();
        });
    }
};
