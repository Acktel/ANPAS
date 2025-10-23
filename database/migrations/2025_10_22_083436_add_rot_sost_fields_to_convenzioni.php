<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('convenzioni', function (Blueprint $table) {
            // ON/OFF della funzionalità sulla singola convenzione
            $table->boolean('abilita_rot_sost')
                ->default(false)
                ->after('ordinamento')
                ->comment('Abilita calcolo Rotazione/Sostitutivi');

            // Strategia: auto (decide con soglia 98%), rotazione, sostitutivi, disattivo
            $table->enum('mod_calcolo_rot_sost', ['auto','rotazione','sostitutivi','disattivo'])
                ->default('auto')
                ->after('abilita_rot_sost')
                ->comment('Modalità calcolo Rotazione/Sostitutivi');
        });
    }

    public function down(): void
    {
        Schema::table('convenzioni', function (Blueprint $table) {
            $table->dropColumn(['abilita_rot_sost','mod_calcolo_rot_sost']);
        });
    }
};
