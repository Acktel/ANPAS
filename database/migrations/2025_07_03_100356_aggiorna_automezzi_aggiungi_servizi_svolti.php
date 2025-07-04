<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->integer('ServiziSvoltiTotali')->default(0)->after('KmTotali');
        });
    }

    public function down(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn('ServiziSvoltiTotali');
        });
    }
};
