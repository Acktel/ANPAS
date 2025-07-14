<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LivelloMansione extends Model
{
    protected $table = 'livello_mansione';
    protected $fillable = ['nome'];

    public static function getAll() {
        return DB::table('livello_mansione')->orderBy('nome')->get();
    }

    public static function createLivello(array $data) {
        return DB::table('livello_mansione')->insertGetId([
            'nome' => $data['nome'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function deleteById(int $id): bool {
        return DB::table('livello_mansione')->where('id', $id)->delete() > 0;
    }
}
