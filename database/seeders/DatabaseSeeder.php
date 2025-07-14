<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Associazione;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(createAssociazioni::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(CreateRole ::class);
        $this->call(AnniSeeder::class);
        $this->call(ConvenzioniSeeder::class);
        $this->call(AutomezziSeeder::class);
        $this->call(TipologiaRiepilogoSeeder::class);
        $this->call(RiepilogoDatiSeeder::class);
        $this->call(AssociazioniAdminSeeder::class);        
        $this->call(RiepilogoDati2Seeder::class);
        $this->call(QualificheSeeder::class);
        $this->call(DipendentiSeeder::class);
        $this->call(DipendenteFittizioSeeder::class);
        $this->call(ContrattiSeeder::class);
        $this->call(AdminSeeder::class);
    }

    /*PER PRODUZIONE
        public function run(): void 
    {
        $this->call(createAssociazioni::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(CreateRole ::class);
        $this->call(AnniSeeder::class);
      //  $this->call(ConvenzioniSeeder::class);
      //  $this->call(AutomezziSeeder::class);
      //  $this->call(TipologiaRiepilogoSeeder::class);
      //  $this->call(RiepilogoDatiSeeder::class);
        $this->call(AssociazioniAdminSeeder::class);        
      //  $this->call(RiepilogoDati2Seeder::class);
      //  $this->call(QualificheSeeder::class);
      //  $this->call(DipendentiSeeder::class);
        $this->call(DipendenteFittizioSeeder::class);
        $this->call(AdminSeeder::class);
    }
    
    
    
    
    */

}
