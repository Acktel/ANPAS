<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Convenzione {
    protected const TABLE = 'convenzioni';

    /**
     * Tutte le convenzioni per anno, con join associazione (solo ruoli alti).
     */
    public static function getAll(?int $anno = null): Collection {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        $sql = "
            SELECT
                c.idConvenzione,
                s.Associazione,
                c.idAnno,
                c.Convenzione,
                c.materiale_fornito_asl,
                c.abilita_rot_sost,
                c.created_at
            FROM " . self::TABLE . " AS c
            JOIN associazioni AS s ON c.idAssociazione = s.idAssociazione
            WHERE c.idAnno = :anno
            ORDER BY s.Associazione, c.Convenzione
        ";

        return collect(DB::select($sql, ['anno' => $anno]));
    }

    /**
     * Convenzioni per anno e (opzionalmente) filtro utente.
     */
    public static function getByAnno(int $anno, $userOrIdAssociazione = null): Collection {
        // Determina l'id associazione in modo flessibile
        $idAssociazione = null;

        if (is_int($userOrIdAssociazione)) {
            $idAssociazione = $userOrIdAssociazione;
        } elseif (is_object($userOrIdAssociazione) && method_exists($userOrIdAssociazione, 'hasAnyRole')) {
            // era la vecchia firma: User|null
            /** @var \App\Models\User $user */
            $user = $userOrIdAssociazione;
            if (!$user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
                $idAssociazione = (int) $user->IdAssociazione;
            }
        }

        // Costruisci SQL con filtro opzionale su idAssociazione
        $baseSql = "
        SELECT
            c.idConvenzione,
            c.idAssociazione,
            c.idAnno,
            c.Convenzione,
            c.materiale_fornito_asl,
            c.abilita_rot_sost,
            c.created_at
        FROM " . self::TABLE . " AS c
        WHERE c.idAnno = :anno
    ";

        $params = ['anno' => $anno];

        if (!is_null($idAssociazione)) {
            $baseSql .= " AND c.idAssociazione = :idAssociazione";
            $params['idAssociazione'] = $idAssociazione;
        }

        $baseSql .= " ORDER BY c.Convenzione";

        return collect(DB::select($baseSql, $params));
    }


    /**
     * Convenzioni per specifica associazione (e anno).
     */
    public static function getByAssociazione(int $idAssociazione, ?int $idAnno = null): Collection {
        $idAnno = $idAnno ?? session('anno_riferimento', now()->year);

        $sql = "
            SELECT
                c.idConvenzione,
                c.idAssociazione,
                c.idAnno,
                c.Convenzione,
                c.materiale_fornito_asl,
                
                c.created_at
            FROM " . self::TABLE . " AS c
            WHERE c.idAssociazione = :idAssociazione
              AND c.idAnno = :idAnno
            ORDER BY c.Convenzione
        ";

        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
        ]));
    }

    /**
     * Singola convenzione per ID.
     */
    public static function getById(int $id): ?object {
        return DB::selectOne("SELECT * FROM " . self::TABLE . " WHERE idConvenzione = :id LIMIT 1", ['id' => $id]);
    }

    /**
     * Crea una nuova convenzione.
     * Accetta: idAssociazione, idAnno, Convenzione, (opz.) lettera_identificativa, note, materiale_fornito_asl
     */
    public static function createConvenzione(array $data): int {
        $now = now()->toDateTimeString();

        $maxOrd = DB::table(self::TABLE)
            ->where('idAssociazione', $data['idAssociazione'])
            ->where('idAnno', $data['idAnno'])
            ->max('ordinamento');

        $ordinamento = is_null($maxOrd) ? 0 : $maxOrd + 1;

        DB::insert("INSERT INTO " . self::TABLE . "
            (idAssociazione, idAnno, Convenzione, lettera_identificativa, note, materiale_fornito_asl, ordinamento, created_at, updated_at)
            VALUES
            (:idAssociazione, :idAnno, :Convenzione, :lettera_identificativa, :note, :materiale_fornito_asl, :ordinamento, :created_at, :updated_at)", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'] ?? null,
            'note'                   => $data['note'] ?? null,
            'materiale_fornito_asl'  => (int) ($data['materiale_fornito_asl'] ?? 0),
            'ordinamento'            => $ordinamento,
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * Aggiorna convenzione.
     * Accetta: idAssociazione, idAnno, Convenzione, (opz.) lettera_identificativa, note, materiale_fornito_asl
     */
    public static function updateConvenzione(int $id, array $data): void {
        $now = Carbon::now()->toDateTimeString();

        DB::update("UPDATE " . self::TABLE . "
            SET
                idAssociazione         = :idAssociazione,
                idAnno                 = :idAnno,
                Convenzione            = :Convenzione,
                lettera_identificativa = :lettera_identificativa,
                note                   = :note,
                materiale_fornito_asl  = :materiale_fornito_asl,
                abilita_rot_sost       = :abilita_rot_sost,
                updated_at             = :updated_at
            WHERE idConvenzione = :id", [
            'idAssociazione'         => $data['idAssociazione'],
            'idAnno'                 => $data['idAnno'],
            'Convenzione'            => $data['Convenzione'],
            'lettera_identificativa' => $data['lettera_identificativa'] ?? null,
            'note'                   => $data['note'] ?? null,
            'materiale_fornito_asl'  => (int) ($data['materiale_fornito_asl'] ?? 0),
            'abilita_rot_sost'       => (int) ($data['abilita_rot_sost'] ?? 0),
            'updated_at'             => $now,
            'id'                     => $id,
        ]);
    }

    /**
     * Cancella convenzione.
     */
    public static function deleteConvenzione(int $id): void {
        DB::delete("DELETE FROM " . self::TABLE . " WHERE idConvenzione = ?", [$id]);
    }

    /**
     * Listing per pagina index, con nome associazione e flag materiale_fornito_asl.
     */
    public static function getWithAssociazione($idAssociazione, $anno): \Illuminate\Support\Collection {
        $sql = "
            SELECT 
                c.idConvenzione,
                c.idAssociazione,
                c.idAnno,
                c.Convenzione,
                c.ordinamento,
                c.created_at,
                c.updated_at,
                c.materiale_fornito_asl,
                a.Associazione,
                GROUP_CONCAT(asl.Nome ORDER BY asl.Nome SEPARATOR ', ') AS AziendeSanitarie
            FROM convenzioni AS c
            JOIN associazioni AS a ON a.idAssociazione = c.idAssociazione
            LEFT JOIN azienda_sanitaria_convenzione AS asco ON c.idConvenzione = asco.idConvenzione
            LEFT JOIN aziende_sanitarie AS asl ON asco.idAziendaSanitaria = asl.idAziendaSanitaria
            WHERE c.idAssociazione = :idAssociazione
              AND c.idAnno = :idAnno
            GROUP BY 
                c.idConvenzione, c.idAssociazione, c.idAnno, c.Convenzione,
                c.ordinamento, c.created_at, c.updated_at, c.materiale_fornito_asl, a.Associazione
            ORDER BY c.ordinamento, c.idConvenzione
        ";

        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $anno,
        ]));
    }

    /**
     * Ritorna TRUE se esiste almeno una convenzione col flag attivo per (associazione, anno).
     */
    public static function checkMaterialeSanitario(?int $idAssociazione, int $idAnno): bool {
        $q = DB::table('convenzioni')->where('idAnno', $idAnno);
        if (!is_null($idAssociazione)) {
            $q->where('idAssociazione', $idAssociazione);
        }
        return $q->where('materiale_fornito_asl', 1)->exists();
    }

    /**
     * Convenzioni per associazione/anno (utility).
     */
    public static function getByAssociazioneAnno(?int $idAssociazione, int $idAnno): Collection {
        $query = DB::table(self::TABLE)->where('idAnno', $idAnno);

        if (!is_null($idAssociazione)) {
            $query->where('idAssociazione', $idAssociazione);
        }

        return $query->orderBy('Convenzione')->get();
    }

    public static function getMezzoTitolare(int $idConvenzione): ?object {
        // Mezzo titolare
        $sql = "
        SELECT a.idAutomezzo, a.Targa, a.CodiceIdentificativo, ak.KMPercorsi AS km_titolare
        FROM automezzi_km AS ak
        JOIN automezzi AS a ON a.idAutomezzo = ak.idAutomezzo
        WHERE ak.idConvenzione = :idConvenzione
          AND ak.is_titolare = 1
        LIMIT 1";
        $titolare = DB::selectOne($sql, ['idConvenzione' => $idConvenzione]);

        if (!$titolare) {
            return null;
        }

        // Km totali per convenzione
        $tot = DB::selectOne("
        SELECT COALESCE(SUM(KMPercorsi),0) AS km_totali
        FROM automezzi_km
        WHERE idConvenzione = :idConvenzione", ['idConvenzione' => $idConvenzione]);

        $kmTotali = (float) ($tot->km_totali ?? 0);
        $perc = $kmTotali > 0 ? round(($titolare->km_titolare / $kmTotali) * 100, 2) : 0;

        return (object) [
            'idAutomezzo'          => $titolare->idAutomezzo,
            'Targa'                => $titolare->Targa,
            'CodiceIdentificativo' => $titolare->CodiceIdentificativo,
            'km_titolare'          => $titolare->km_titolare,
            'km_totali'            => $kmTotali,
            'percentuale'          => $perc,
        ];
    }
}
