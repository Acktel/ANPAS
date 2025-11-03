<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('costi_automezzi', function (Blueprint $table) {
            // testo libero, opzionale, posizionato dopo 'Ossigeno'
            $table->text('Note')->nullable()->after('Ossigeno');
        });
    }

    public function down(): void
    {
        Schema::table('costi_automezzi', function (Blueprint $table) {
            $table->dropColumn('Note');
        });
    }
};
