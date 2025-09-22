<?php


namespace App\Helpers;


use DateTime;
use Illuminate\Support\Facades\DB;
use stdClass;


class SDB
{
    public function insert(string $tablename, array $fields): ?stdClass
    {


        $fields['created_at'] = (new DateTime('now', new \DateTimeZone(env('APP_TIMEZONE', 'UTC'))))->format('Y-m-d H:i:s');
        $fields['updated_at'] = $fields['created_at'];


        $query = "INSERT INTO {$tablename} (";
        foreach ($fields as $fieldname => $fieldvalue) {
            $query .= "{$fieldname},";
        }
        $query =  substr($query, 0, -1) . ") VALUES (";


        foreach ($fields as $fieldname => $fieldvalue) {
            $query .= ":{$fieldname},";
        }
        $query =  substr($query, 0, -1) . ")";


        DB::beginTransaction();
        DB::statement($query, $fields);
        $id = DB::select("SELECT LAST_INSERT_ID() AS id");
        DB::commit();


        return DB::select($this->generateQueryFromReturn($tablename, $fields), [$id[0]->id])[0];
    }



    public function modify(string $tablename, ?int $id, array $fields): ?stdClass
    {
        $fields['updated_at'] = (new DateTime('now', new \DateTimeZone(env('APP_TIMEZONE', 'UTC'))))->format('Y-m-d H:i:s');


        $query = "UPDATE {$tablename} SET ";
        foreach ($fields as $fieldname => $fieldvalue) {
            $query .= "{$fieldname} = :{$fieldname},";
        }
        $query =  substr($query, 0, -1);
        $query .=  " WHERE id = {$id}";
        DB::statement($query, $fields);


        return DB::select($this->generateQueryFromReturn($tablename, $fields), [$id])[0];
    }


    private function generateQueryFromReturn(string $tablename, array $fields): string
    {


        $query = "SELECT id,";
        foreach ($fields as $fieldname => $fieldvalue) {
            $query .= " {$fieldname},";
        }
        return substr($query, 0, -1) . " FROM {$tablename} WHERE id = ? LIMIT 1";
    }
}