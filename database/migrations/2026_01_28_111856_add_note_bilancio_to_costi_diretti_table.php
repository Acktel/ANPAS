<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteBilancioToCostiDirettiTable extends Migration
{
    public function up()
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->text('note_bilancio')->nullable()->after('note');
        });
    }

    public function down()
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropColumn('note_bilancio');
        });
    }
}
