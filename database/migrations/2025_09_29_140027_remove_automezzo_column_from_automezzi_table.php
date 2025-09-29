<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn('Automezzo');
        });
    }

    public function down(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->string('Automezzo')->nullable(); 
            // tipo/nullable adattali a com’era prima
        });
    }
};
