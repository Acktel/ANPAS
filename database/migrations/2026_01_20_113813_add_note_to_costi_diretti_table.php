<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteToCostiDirettiTable extends Migration
{
    public function up()
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            // mettila dove vuoi, qui dopo ammortamento
            $table->text('note')->nullable()->after('bilancio_consuntivo');
        });
    }

    public function down()
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}
