<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomezzoKmRiferimento extends Model
{
    use HasFactory;

    protected $table = 'automezzi_km_riferimento';

    protected $primaryKey = 'idAutomezzoKmRif';

    protected $fillable = [
        'idAutomezzo',
        'idAnno',
        'KmRiferimento',
    ];

    public function automezzo() {
        return $this->belongsTo(Automezzo::class, 'idAutomezzo');
    }
}
