<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->unique(
                ['idAssociazione', 'idAnno', 'idConvenzione', 'idSezione', 'voce'],
                'costi_diretti_unique_key'
            );
        });
    }

    public function down(): void {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropUnique('costi_diretti_unique_key');
        });
    }

};
