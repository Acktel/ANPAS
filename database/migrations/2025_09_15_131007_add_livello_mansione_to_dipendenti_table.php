<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('dipendenti', function (Blueprint $table) {
            $table->string('LivelloMansione', 255)->nullable()->after('ContrattoApplicato');
        });
    }

    public function down()
    {
        Schema::table('dipendenti', function (Blueprint $table) {
            $table->dropColumn('LivelloMansione');
        });
    }
};
