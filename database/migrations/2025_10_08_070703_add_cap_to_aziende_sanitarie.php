<?php

// database/migrations/2025_10_08_000000_add_cap_to_aziende_sanitarie.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('aziende_sanitarie', function (Blueprint $t) {
            $t->string('cap', 10)->nullable()->after('citta');
            $t->index('cap');
        });
    }
    public function down(): void {
        Schema::table('aziende_sanitarie', function (Blueprint $t) {
            $t->dropIndex(['cap']);
            $t->dropColumn('cap');
        });
    }
};

