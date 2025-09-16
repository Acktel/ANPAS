<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use iio\libmergepdf\Merger;

class MergeDocumentsByIdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bundleDocumentoId,   // id del documento "bundle" da aggiornare
        public array $childrenDocumentoIds // tutti i documenti generati dai 17 job (in ordine)
    ) { $this->onQueue('pdf'); }

    public function handle(): void
    {
        // metto il bundle in processing
        DB::table('documenti_generati')
            ->where('id', $this->bundleDocumentoId)
            ->update(['stato' => 'processing', 'updated_at' => now()]);

        $docs = DB::table('documenti_generati')
            ->whereIn('id', $this->childrenDocumentoIds)
            ->orderByRaw('FIELD(id,'.implode(',', $this->childrenDocumentoIds).')')
            ->get(['percorso_file']);

        $merger = new Merger();
        $added  = 0;

        foreach ($docs as $d) {
            $rel = (string) $d->percorso_file;
            if (!$rel) continue;
            if (!Storage::disk('public')->exists($rel)) continue;
            $merger->addFile(Storage::disk('public')->path($rel));
            $added++;
        }

        if ($added === 0) {
            DB::table('documenti_generati')
              ->where('id', $this->bundleDocumentoId)
              ->update(['stato' => 'error', 'updated_at' => now()]);
            return;
        }

        $pdf = $merger->merge();
        $bundle = DB::table('documenti_generati')->where('id', $this->bundleDocumentoId)->first();

        $path = sprintf('documenti/BUNDLE_TUTTI_%d_%d_%d.pdf',
            (int)$bundle->idAssociazione, (int)$bundle->idAnno, $this->bundleDocumentoId
        );
        Storage::disk('public')->put($path, $pdf);

        DB::table('documenti_generati')
            ->where('id', $this->bundleDocumentoId)
            ->update([
                'percorso_file' => $path,
                'stato'         => 'ready',
                'generato_il'   => now(),
                'updated_at'    => now(),
            ]);
    }
}
