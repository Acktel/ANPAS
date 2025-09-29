<?php

// database/migrations/2025_09_25_000000_add_ammortamento_to_costi_diretti.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->decimal('ammortamento', 12, 2)->default(0)->after('costo');
        });
    }
    public function down(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropColumn('ammortamento');
        });
    }
};
