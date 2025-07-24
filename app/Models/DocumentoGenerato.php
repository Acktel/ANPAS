<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentoGenerato extends Model {
    protected $table = 'documenti_generati';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idUtente',
        'idAssociazione',
        'idAnno',
        'tipo_documento',
        'nome_file',
        'percorso_file',
        'generato_il',
    ];

    // Accessor per il percorso completo del file (opzionale)
    public function getFullPathAttribute(): string {
        return storage_path('app/' . $this->percorso_file);
    }

    // Relazioni (opzionali)
    public function utente() {
        return $this->belongsTo(\App\Models\User::class, 'idUtente');
    }

    public function associazione() {
        return $this->belongsTo(\App\Models\Associazione::class, 'idAssociazione');
    }
    public function getUrlAttribute(): string {
        return Storage::url((string) $this->attributes['percorso_file']);
    }
}

