<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costi_automezzi', function (Blueprint $table) {
            $table->decimal('MaterialiSanitariConsumo', 12, 2)
                  ->default(0)
                  ->after('AmmortamentoSanitaria');

            $table->decimal('Ossigeno', 12, 2)
                  ->default(0)
                  ->after('MaterialiSanitariConsumo');
        });
    }

    public function down(): void
    {
        Schema::table('costi_automezzi', function (Blueprint $table) {
            $table->dropColumn(['MaterialiSanitariConsumo', 'Ossigeno']);
        });
    }
};
