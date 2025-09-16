<?php
// app/Support/Batches/FinalizeBundleCallback.php
namespace App\Support\Batches;

use Illuminate\Bus\Batch;
use App\Jobs\MergeBundlePdfsJob;

class FinalizeBundleCallback
{
    public function __construct(
        protected int $bundleId,
        protected int $idAssociazione,
        protected int $anno,
    ) {}

    public function __invoke(Batch $batch): void
    {
        // quando il batch finisce con successo, avvia il merge
        MergeBundlePdfsJob::dispatch(
            $this->bundleId,
            $this->idAssociazione,
            $this->anno
        )->onQueue('pdf');
    }
}
