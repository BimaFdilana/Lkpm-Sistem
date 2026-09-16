<?php

namespace App\Jobs;

use App\ImportDataProcessor;
use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(public ImportBatch $batch) {}

    /**
     * Execute the job.
     */
    public function handle(ImportDataProcessor $importDataProcessor): void
    {
        $this->batch->update(['status' => 'processing']);
        $summary = $importDataProcessor->process($this->batch);

        $this->batch->update([
            'status' => 'ready',
            'accepted_rows' => $summary['accepted_rows'],
            'rejected_rows' => $summary['rejected_rows'],
            'summary' => $summary,
        ]);

    }

    public function failed(Throwable $exception): void
    {
        $this->batch->update([
            'status' => 'failed',
            'summary' => ['message' => 'Pemrosesan data gagal. Periksa log aplikasi.'],
        ]);
    }
}
