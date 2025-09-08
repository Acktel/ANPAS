<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('documenti_generati', function (Blueprint $t) {
      $t->string('stato', 20)->default('queued');  // queued|processing|ready|error
      $t->text('errore')->nullable();
      $t->timestamp('started_at')->nullable();
      $t->timestamp('finished_at')->nullable();
      $t->unsignedBigInteger('file_size')->nullable();
      $t->string('mime', 100)->nullable();
    });
  }
  public function down(): void {
    Schema::table('documenti_generati', function (Blueprint $t) {
      $t->dropColumn(['stato','errore','started_at','finished_at','file_size','mime']);
    });
  }
};

