<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('documenti_generati', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idAssociazione');
            $table->foreign('idAssociazione')
                  ->references('IdAssociazione')
                  ->on('associazioni')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('idUtente')->nullable();
            $table->foreign('idUtente')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->unsignedInteger('idAnno');
            $table->string('tipo_documento'); // es. 'registro', 'distinta', 'criteri'
            $table->timestamp('generato_il')->nullable(); // quando è stato generato

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('documenti_generati');
        Schema::enableForeignKeyConstraints();
    }
};
