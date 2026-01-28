<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteToCostiRadioTable extends Migration
{
    public function up()
    {
        Schema::table('costi_radio', function (Blueprint $table) {
            $table->text('note')->nullable()->after('AmmortamentoImpiantiRadio');
        });
    }

    public function down()
    {
        Schema::table('costi_radio', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
