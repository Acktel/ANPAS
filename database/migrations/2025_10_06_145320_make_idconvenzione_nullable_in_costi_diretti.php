<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->unsignedBigInteger('idConvenzione')->nullable()->change();
        });
    }
    public function down() {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->unsignedBigInteger('idConvenzione')->nullable(false)->change();
        });
    }
};

