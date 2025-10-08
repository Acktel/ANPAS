<?php
// database/migrations/2025_01_01_100000_add_cap_to_associazioni_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->string('cap', 10)->nullable()->after('citta');
        });

    }

    public function down(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->dropColumn('cap');
        });
    }
};
