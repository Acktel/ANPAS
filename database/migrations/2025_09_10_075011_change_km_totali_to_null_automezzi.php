<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: droppa la colonna
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn('KmTotali');
        });

        // Step 2: ricrea la colonna come nullable
        Schema::table('automezzi', function (Blueprint $table) {
            $table->integer('KmTotali')->nullable();
        });
    }

    public function down(): void
    {
        // Ripristina la colonna come NOT NULL (ipotizzando che fosse così)
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn('KmTotali');
        });

        Schema::table('automezzi', function (Blueprint $table) {
            $table->integer('KmTotali'); // NOT NULL di default
        });
    }
};