<?php

// database/migrations/2025_09_30_000000_add_ordinamento_attivo_to_qualifiche.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('qualifiche', function (Blueprint $table) {
            if (!Schema::hasColumn('qualifiche', 'ordinamento')) {
                $table->integer('ordinamento')->default(0)->after('nome');
            }
            if (!Schema::hasColumn('qualifiche', 'attivo')) {
                $table->boolean('attivo')->default(true)->after('ordinamento');
            }
        });
    }

    public function down(): void {
        Schema::table('qualifiche', function (Blueprint $table) {
            if (Schema::hasColumn('qualifiche', 'attivo')) {
                $table->dropColumn('attivo');
            }
            if (Schema::hasColumn('qualifiche', 'ordinamento')) {
                $table->dropColumn('ordinamento');
            }
        });
    }
};

