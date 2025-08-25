<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->decimal('bilancio_consuntivo', 12, 2)->default(0)->after('costo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropColumn('bilancio_consuntivo');
        });
    }
};
