<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteToCostiPersonaleTable extends Migration
{
    public function up()
    {
        Schema::table('costi_personale', function (Blueprint $table) {
            $table->text('note')->nullable()->after('Totale');
        });
    }

    public function down()
    {
        Schema::table('costi_personale', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
