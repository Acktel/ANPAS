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
        Schema::table('costi_materiale_sanitario', function (Blueprint $table) {
            if (!Schema::hasColumn('costi_materiale_sanitario', 'note')) {
                $table->text('note')->nullable()->after('TotaleBilancio');
            }
        });
    }

    public function down()
    {
        Schema::table('costi_materiale_sanitario', function (Blueprint $table) {
            if (Schema::hasColumn('costi_materiale_sanitario', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
