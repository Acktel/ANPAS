<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoDiretto extends Model
{
    protected $table = 'costi_diretti';
    protected $primaryKey = 'idCosto';
    public $timestamps = true;

    protected $fillable = [
        'idAssociazione',
        'idAnno',
        'idConvenzione',
        'idSezione',
        'idVoceConfig',
        'voce',
        'costo',
        'ammortamento',     
        'note',    
    ];

    protected $casts = [
        'costo'              => 'float',
        'ammortamento'       => 'float',
        'bilancio_consuntivo'=> 'float', 
    ];
}
