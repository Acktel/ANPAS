<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use iio\libmergepdf\Merger;
use Throwable;

class MergeBundlePdfsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteChildrenAfterMerge = true;

    public function __construct(
        public int $bundleId,
        public int $idAssociazione,
        public int $anno,
    ) {
        $this->onQueue('pdf');
    }

    public function middleware(): array
    {
        // evita merge concorrenti dello stesso bundle
        return [
            (new WithoutOverlapping("merge-bundle-{$this->bundleId}"))->expireAfter(300)->dontRelease(),
        ];
    }

    public function handle(): void
    {
        // prendi SOLO i figli di questo bundle, in ordine di creazione
        $children = DB::table('documenti_generati')
            ->where('parent_id', $this->bundleId)
            ->orderBy('id')
            ->get([
                'id', 'percorso_file', 'stato'
            ]);

        $merger = new Merger();
        $added  = 0;

        foreach ($children as $child) {
            $rel = (string) ($child->percorso_file ?? '');
            if ($rel === '') {
                continue;
            }
            if (!Storage::disk('public')->exists($rel)) {
                continue;
            }
            if (Storage::disk('public')->size($rel) <= 0) {
                continue;
            }

            $merger->addFile(Storage::disk('public')->path($rel));
            $added++;
        }

        if ($added === 0) {
            // niente da unire → segna errore e termina
            DB::table('documenti_generati')->where('id', $this->bundleId)->update([
                'stato'      => 'error',
                'updated_at' => now(),
            ]);
            
            Log::warning("MergeBundlePdfsJob: nessun PDF valido trovato per bundle {$this->bundleId}");
            return;
        }

        $bundlePath = sprintf(
            'documenti/bundle_all_%d_%d_%d.pdf',
            $this->idAssociazione,
            $this->anno,
            $this->bundleId
        );

        // crea il PDF unificato
        $output = $merger->merge(); // string binaria del pdf
        Storage::disk('public')->put($bundlePath, $output);

        // aggiorna il bundle come pronto
        DB::table('documenti_generati')->where('id', $this->bundleId)->update([
            'percorso_file' => $bundlePath,
            'stato'         => 'ready',
            'generato_il'   => now(),
            'updated_at'    => now(),
        ]);

        // cleanup opzionale: elimina file e record figli
        if ($this->deleteChildrenAfterMerge) {
            foreach ($children as $child) {
                $rel = (string) ($child->percorso_file ?? '');
                if ($rel && Storage::disk('public')->exists($rel)) {
                    Storage::disk('public')->delete($rel);
                }
            }
            DB::table('documenti_generati')->where('parent_id', $this->bundleId)->delete();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error("MergeBundlePdfsJob failed", [
            'bundleId' => $this->bundleId,
            'error'    => $e->getMessage(),
        ]);

        DB::table('documenti_generati')->where('id', $this->bundleId)->update([
            'stato'      => 'error',
            'updated_at' => now(),
        ]);
    }
}
