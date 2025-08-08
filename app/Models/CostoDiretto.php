<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoDiretto extends Model {
    protected $table = 'costi_diretti';

    protected $primaryKey = 'idCosto';

    protected $fillable = [
        'idAssociazione',
        'idAnno',
        'idConvenzione',
        'idSezione',
        'voce',
        'costo',
        'bilancio_consuntivo',
    ];

    protected $casts = [
        'costo' => 'float',
        'bilancio_consuntivo' => 'float',
    ];

    public $timestamps = true;
}
