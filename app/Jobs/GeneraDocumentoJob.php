<?php

namespace App\Jobs;

use App\Services\ExcelGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\Documento;
use App\Models\Automezzo;
use App\Models\Dipendente;
use App\Models\DocumentoGenerato;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Illuminate\Support\Facades\Log;

class GeneraDocumentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $idAssociazione;
    protected $idAnno;
    protected $utenteId;

    public function __construct(int $idAssociazione, int $idAnno, int $utenteId)
    {
        $this->idAssociazione = $idAssociazione;
        $this->idAnno = $idAnno;
        $this->utenteId = $utenteId;
    }

    public function handle(): void
    {
        $registro = Documento::getRegistroData($this->idAssociazione, $this->idAnno);
        $convenz  = Documento::getConvenzioniData($this->idAssociazione, $this->idAnno);
        $autoz    = Automezzo::getByAssociazione($this->idAssociazione, $this->idAnno);
        $autisti  = Dipendente::getAutisti($this->idAnno);
        $altri    = Dipendente::getAltri($this->idAnno);

        // 1. Crea il file Excel
        $spreadsheet = (new ExcelGeneratorService())->generaDocumento($registro, $convenz, $autoz, $autisti, $altri);

        $filename = "registro_{$this->idAssociazione}_{$this->idAnno}_" . now()->timestamp . ".xls";
        $path = "documenti/{$filename}";

        $writer = new Xls($spreadsheet);

        $tempPath = tempnam(sys_get_temp_dir(), 'xls');
        $writer->save($tempPath);
        $fileContents = file_get_contents($tempPath);
        Storage::disk('public')->put($path, $fileContents); 
        
        // 3. Salva il record in DB
        DocumentoGenerato::create([
            'idUtente'        => $this->utenteId,
            'idAssociazione'  => $this->idAssociazione,
            'idAnno'          => $this->idAnno,
            'tipo_documento'  => 'registro',
            'nome_file'       => $filename,
            'percorso_file'   => $path,
            'generato_il'     => now(),
        ]);
    }
}
