<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostiPersonale extends Model {
    protected $table = 'costi_personale';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idDipendente',
        'Retribuzioni',
        'OneriSociali',
        'TFR',
        'Consulenze',
        'Totale',
        'idAnno'
    ];

    protected $casts = [
        'Retribuzioni' => 'float',
        'OneriSociali' => 'float',
        'TFR' => 'float',
        'Consulenze' => 'float',
        'Totale' => 'float',
    ];

    public function dipendente() {
        return $this->belongsTo(Dipendente::class, 'idDipendente', 'idDipendente');
    }

    public static function getByDipendente(int $idDipendente, int $anno) {
        return self::where('idDipendente', $idDipendente)
                   ->where('idAnno', $anno)
                   ->first();
    }
}
