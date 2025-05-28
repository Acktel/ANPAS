<?php
// database/migrations/xxxx_xx_xx_alter_associazioni_add_auth_fields.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->string('email')->unique()->after('Associazione');
            $table->string('password')->after('email');
            $table->string('provincia', 100)->after('password');
            $table->string('città', 100)->after('provincia');
            // se vuoi gestire "remember me"
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->dropColumn(['email','password','provincia','città','remember_token']);
        });
    }
};
