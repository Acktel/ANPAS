<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rende le colonne nullable (mantieni o adatta le lunghezze alla tua struttura)
        DB::statement("ALTER TABLE `aziende_sanitarie` MODIFY `provincia` VARCHAR(255) NULL");
        DB::statement("ALTER TABLE `aziende_sanitarie` MODIFY `citta` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        // Evita errori nel tornare a NOT NULL: rimpiazza gli eventuali NULL con stringa vuota
        DB::table('aziende_sanitarie')->whereNull('provincia')->update(['provincia' => '']);
        DB::table('aziende_sanitarie')->whereNull('citta')->update(['citta' => '']);

        DB::statement("ALTER TABLE `aziende_sanitarie` MODIFY `provincia` VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE `aziende_sanitarie` MODIFY `citta` VARCHAR(255) NOT NULL");
    }
};
