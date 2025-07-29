<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('costi_ossigeno', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAssociazione');
            $table->integer('idAnno');

            $table->decimal('TotaleBilancio', 12, 2)->default(0);

            $table->timestamps();

            // 🔗 Foreign key
            $table->foreign('idAssociazione')
                ->references('IdAssociazione')
                ->on('associazioni')
                ->onDelete('cascade');

            // 🔒 Unicità per associazione+anno
            $table->unique(['idAssociazione', 'idAnno']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('costi_ossigeno');
    }
};
