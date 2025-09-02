<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenzioni', function (Blueprint $table) {
            $table->text('note')->nullable()->after('lettera_identificativa');
        });
    }

    public function down(): void
    {
        Schema::table('convenzioni', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
