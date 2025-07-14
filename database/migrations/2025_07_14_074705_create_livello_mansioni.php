<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('livello_mansione', function (Blueprint $table) {
            $table->id();
            $table->string('nome'); // es: SOCCORRITORE, AUTISTA
           // $table->string('livello_mansione', 10); // es: C2, C4, D1
            $table->timestamps();

            $table->unique(['nome'], 'livello_mansione_unique');
        });
    }

    public function down(): void
    {       
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('livello_mansione');
        Schema::enableForeignKeyConstraints();
    }
};
