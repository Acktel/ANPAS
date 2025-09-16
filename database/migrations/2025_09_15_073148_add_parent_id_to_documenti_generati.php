<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('documenti_generati', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_id')->nullable()->index()->after('id');
        });
    }
    public function down(): void {
        Schema::table('documenti_generati', function (Blueprint $t) {
            $t->dropColumn('parent_id');
        });
    }
};
