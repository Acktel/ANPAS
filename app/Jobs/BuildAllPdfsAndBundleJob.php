<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Support\Batches\FinalizeBundleCallback;
use App\Support\Batches\FailBundleCallback;

class BuildAllPdfsAndBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bundleId,
        public int $idAssociazione,
        public int $anno,
        public int $utenteId,
    ) {
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        // segna bundle "processing"
        DB::table('documenti_generati')->where('id', $this->bundleId)->update([
            'stato'      => 'processing',
            'updated_at' => now(),
        ]);

        // 🔒 cleanup: rimuovi eventuali figli “vecchi” legati a questo bundle
        $oldChildren = DB::table('documenti_generati')
            ->where('parent_id', $this->bundleId)
            ->pluck('id');

        if ($oldChildren->isNotEmpty()) {
            DB::table('documenti_generati')->whereIn('id', $oldChildren)->delete();
        }

        // fabbriche dei 15 job (aggiungi/rimuovi a piacere)
        $factories = [
            fn($id) => new DistintaImputazioneCostiPdfJob($id),
            fn($id) => new GeneraCostiAutomezziSanitariPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraCostiPersonalePdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraCostiRadioPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraDistintaKmPercorsiPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraImputazioniMaterialeEOssigenoPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRapportiRicaviPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRegistroAutomezziPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRiepilogoCostiPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRiepilogoRipCostiAutomezziPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRipartizionePersonalePdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraRipVolontariScnPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraServiziSvoltiOssigenoPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new GeneraServiziSvoltiPdfJob($id, $this->idAssociazione, $this->anno, $this->utenteId),
            fn($id) => new RiepiloghiDatiECostiPdfJob($id, $this->idAssociazione, $this->anno),
        ];

        // crea i record “child” legati al bundle e prepara il batch
        $batchJobs = [];
        foreach ($factories as $factory) {
            $childId = DB::table('documenti_generati')->insertGetId([
                'parent_id'      => $this->bundleId,            // 🔑 legame
                'idAssociazione' => $this->idAssociazione,
                'idAnno'         => $this->anno,
                'idUtente'       => $this->utenteId,
                'tipo_documento' => 'child',
                'stato'          => 'queued',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $batchJobs[] = $factory($childId)->onQueue('pdf');
        }

        // avvia il batch; al termine con successo partirà il merge per questo bundle
        Bus::batch($batchJobs)
            ->name("bundle-{$this->bundleId}")
            ->allowFailures()                   // opzionale: non fermare tutto se un figlio fallisce
            ->then(new FinalizeBundleCallback(
                $this->bundleId,
                $this->idAssociazione,
                $this->anno
            ))
            ->catch(new FailBundleCallback($this->bundleId))
            ->onConnection('database')
            ->onQueue('pdf')
            ->dispatch();
    }
}
