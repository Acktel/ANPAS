<?php
// app/Support/Batches/FailBundleCallback.php
namespace App\Support\Batches;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FailBundleCallback
{
    public function __construct(protected int $bundleId) {}

    public function __invoke(Batch $batch, Throwable $e): void
    {
        DB::table('documenti_generati')->where('id', $this->bundleId)->update([
            'stato'      => 'error',
            'updated_at' => now(),
        ]);

        Log::error("Bundle {$this->bundleId} failed", ['error' => $e->getMessage()]);
    }
}
