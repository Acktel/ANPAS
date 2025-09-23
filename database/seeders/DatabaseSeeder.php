<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Associazione;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //Seed SQL provincie, comuni, nazioni ecc...
        DB::statement(File::get(database_path('seeders/sql/nazioni.sql')));
        DB::statement(File::get(database_path('seeders/sql/regioni.sql')));
        DB::statement(File::get(database_path('seeders/sql/province.sql')));
        DB::statement(File::get(database_path('seeders/sql/comuni.sql')));
        DB::statement(File::get(database_path('seeders/sql/comuni_validita.sql')));
        DB::statement(File::get(database_path('seeders/sql/cap.sql')));
        DB::statement(File::get(database_path('seeders/sql/comuni_nazioni_cf.sql')));
        DB::statement(File::get(database_path('seeders/sql/comuni_cap.sql')));


     /*   $this->call(createAssociazioni::class);
        $this->call(SuperAdminSeeder::class);
        $this->call(CreateRole ::class);
        $this->call(AnniSeeder::class);
        $this->call(ConvenzioniSeeder::class);
        $this->call(AutomezziSeeder::class);
        $this->call(TipologiaRiepilogoSeeder::class);
        // $this->call(RiepilogoDatiSeeder::class);
        $this->call(AssociazioniAdminSeeder::class);     
        // $this->call(RiepilogoDati2Seeder::class);
        $this->call(MaterialeSanitarioSeeder::class);
        $this->call(QualificheSeeder::class);
        $this->call(DipendentiSeeder::class);
        $this->call(DipendenteFittizioSeeder::class);
        $this->call(ContrattiSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call( RiepilogoVociConfigSeeder::class);
        $this->call( TerCitiesSeeder::class);*/
        $this->call(QualificheSeeder::class);
    }

    //PER PRODUZIONE
       /* public function run(): void 
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
