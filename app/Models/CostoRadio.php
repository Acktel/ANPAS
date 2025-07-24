<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostoRadio extends Model
{
    protected $table = 'costi_radio';

    protected $fillable = [
        'idAssociazione',
        'idAnno',
        'ManutenzioneApparatiRadio',
        'MontaggioSmontaggioRadio118',
        'LocazionePonteRadio',
        'AmmortamentoImpiantiRadio',
    ];
}
