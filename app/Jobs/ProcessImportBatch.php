<?php

namespace App\Jobs;

use App\GoogleDriveStorage;
use App\ImportDataProcessor;
use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessImportBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(public ImportBatch $batch) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('import-batch-'.$this->batch->id))
                ->dontRelease()
                ->expireAfter(1260),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(ImportDataProcessor $importDataProcessor, GoogleDriveStorage $googleDriveStorage): void
    {
        $this->batch->update(['status' => 'processing']);
        $summary = $importDataProcessor->process($this->batch);

        if ($this->batch->drive_file_id !== null) {
            try {
                $googleDriveStorage->archive($this->batch);
                $summary['drive'] = ['state' => 'archived', 'message' => 'File dipindahkan ke folder arsip Google Drive.'];
                $this->batch->drive_state = 'archived';
                $this->batch->drive_error = null;
                $this->batch->drive_moved_at = now();
            } catch (Throwable $exception) {
                report($exception);
                $summary['drive'] = ['state' => 'archive_pending', 'message' => 'Data berhasil diproses, tetapi file belum dapat dipindahkan ke folder arsip.'];
                $this->batch->drive_state = 'archive_pending';
                $this->batch->drive_error = $exception->getMessage();
            }
        }

        $this->batch->status = 'ready';
        $this->batch->accepted_rows = $summary['accepted_rows'];
        $this->batch->rejected_rows = $summary['rejected_rows'];
        $this->batch->summary = $summary;
        $this->batch->save();
    }

    public function failed(Throwable $exception): void
    {
        $summary = ['message' => 'Pemrosesan data gagal. Periksa log aplikasi.'];
        $this->batch->status = 'failed';

        if ($this->batch->drive_file_id !== null) {
            try {
                app(GoogleDriveStorage::class)->markAsFailed($this->batch);
                $summary['drive'] = ['state' => 'failed_folder', 'message' => 'File dipindahkan ke folder impor gagal Google Drive.'];
                $this->batch->drive_state = 'failed_folder';
                $this->batch->drive_error = null;
                $this->batch->drive_moved_at = now();
            } catch (Throwable $driveException) {
                report($driveException);
                $summary['drive'] = ['state' => 'failed_move_pending', 'message' => 'File gagal diproses dan belum dapat dipindahkan ke folder impor gagal.'];
                $this->batch->drive_state = 'failed_move_pending';
                $this->batch->drive_error = $driveException->getMessage();
            }
        }

        $this->batch->summary = $summary;
        $this->batch->save();
    }
}
