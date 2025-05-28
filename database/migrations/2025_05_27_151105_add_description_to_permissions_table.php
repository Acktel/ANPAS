<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
// database/migrations/xxxx_xx_xx_xxxxxx_add_description_to_permissions_table.php
public function up()
{
    Schema::table('permissions', function (Blueprint $table) {
        $table->string('description')->nullable()->after('name');
    });
}

public function down()
{
    Schema::table('permissions', function (Blueprint $table) {
        $table->dropColumn('description');
    });
}

};
