<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class AutomezzoKmRiferimento extends Model {
    use HasFactory;

    protected $table = 'automezzi_km_riferimento';
    protected $primaryKey = 'idAutomezzoKmRif';
    public $timestamps = true;

    protected $fillable = [
        'idAutomezzo',
        'idAnno',
        'KmRiferimento',
    ];

    // Cast automatici (Eloquent restituisce interi)
    protected $casts = [
        'idAutomezzo'   => 'integer',
        'idAnno'        => 'integer',
        'KmRiferimento' => 'integer',
    ];

    public function automezzo() {
        return $this->belongsTo(Automezzo::class, 'idAutomezzo');
    }

    /** Normalizza qualsiasi input a intero non negativo (o 0). */
    private static function toIntKm($v): int {
        if ($v === null || $v === '') return 0;
        // rimuove spazi/virgole, converte a float e poi arrotonda all'intero
        $n = (int) round((float) str_replace([',', ' '], ['.', ''], (string) $v));
        return max(0, $n);
    }

    /**
     * Ritorna KmRiferimento (int) per automezzo+anno, o null se assente.
     */
    public static function getForAutomezzoAnno(int $idAutomezzo, int $idAnno): ?int {
        $val = self::query()
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $idAnno)
            ->value('KmRiferimento');

        return is_null($val) ? null : (int) $val;
    }

    /**
     * Insert semplice (normalizzato a intero).
     */
    public static function insertKmRiferimento(array $data): bool {
        return DB::table('automezzi_km_riferimento')->insert([
            'idAutomezzo'   => (int) $data['idAutomezzo'],
            'idAnno'        => (int) $data['idAnno'],
            'KmRiferimento' => self::toIntKm($data['KmRiferimento']), // 👈 intero
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Upsert (updateOrCreate) con normalizzazione a intero.
     * Utile quando devi garantire un unico record per (idAutomezzo, idAnno).
     */
    public static function updateOrCreateInt(array $keys, $km): self {
        return self::updateOrCreate(
            [
                'idAutomezzo' => (int) $keys['idAutomezzo'],
                'idAnno'      => (int) $keys['idAnno'],
            ],
            [
                'KmRiferimento' => self::toIntKm($km), // 👈 intero
            ]
        );
    }

    public static function deleteByAutomezzo(int $idAutomezzo, ?int $idAnno = null): int {
        $q = self::query()->where('idAutomezzo', $idAutomezzo);
        if (!is_null($idAnno)) $q->where('idAnno', $idAnno);
        return $q->delete();
    }
}
