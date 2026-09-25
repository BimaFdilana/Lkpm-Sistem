<?php

namespace Tests\Feature;

use App\GoogleDriveStorage;
use App\ImportDataProcessor;
use App\Jobs\ProcessImportBatch;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessImportBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_successful_import_is_archived_without_changing_the_ready_status(): void
    {
        $batch = $this->batch();
        $processor = $this->mock(ImportDataProcessor::class);
        $processor->shouldReceive('process')->once()->andReturn(['accepted_rows' => 10, 'rejected_rows' => 1]);
        $drive = $this->mock(GoogleDriveStorage::class);
        $drive->shouldReceive('archive')->once()->withArgs(fn (ImportBatch $value): bool => $value->is($batch))->andReturn(['id' => 'drive-file', 'name' => 'archived.xlsx', 'url' => null]);

        (new ProcessImportBatch($batch))->handle($processor, $drive);

        $batch->refresh();
        $this->assertSame('ready', $batch->status);
        $this->assertSame('archived', $batch->drive_state);
        $this->assertSame(10, $batch->accepted_rows);
        $this->assertSame('archived', $batch->summary['drive']['state']);
        $this->assertNotNull($batch->drive_moved_at);
    }

    public function test_archive_failure_does_not_turn_a_valid_import_into_a_failed_batch(): void
    {
        $batch = $this->batch();
        $processor = $this->mock(ImportDataProcessor::class);
        $processor->shouldReceive('process')->once()->andReturn(['accepted_rows' => 5, 'rejected_rows' => 0]);
        $drive = $this->mock(GoogleDriveStorage::class);
        $drive->shouldReceive('archive')->once()->andThrow(new RuntimeException('Drive sementara tidak tersedia'));

        (new ProcessImportBatch($batch))->handle($processor, $drive);

        $batch->refresh();
        $this->assertSame('ready', $batch->status);
        $this->assertSame('archive_pending', $batch->drive_state);
        $this->assertSame('archive_pending', $batch->summary['drive']['state']);
        $this->assertNotNull($batch->drive_error);
    }

    public function test_failed_import_is_moved_to_the_failed_folder(): void
    {
        $batch = $this->batch();
        $drive = $this->mock(GoogleDriveStorage::class);
        $drive->shouldReceive('markAsFailed')->once()->andReturn(['id' => 'drive-file', 'name' => 'failed.xlsx', 'url' => null]);

        (new ProcessImportBatch($batch))->failed(new RuntimeException('Python gagal'));

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertSame('failed_folder', $batch->drive_state);
        $this->assertSame('failed_folder', $batch->summary['drive']['state']);
    }

    public function test_queue_visibility_is_longer_than_the_import_timeout_and_the_job_has_an_overlap_lock(): void
    {
        $batch = $this->batch();
        $job = new ProcessImportBatch($batch);

        $this->assertSame(1260, config('queue.connections.database.retry_after'));
        $this->assertSame(1200, $job->timeout);
        $this->assertCount(1, $job->middleware());
    }

    private function batch(): ImportBatch
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);

        return ImportBatch::create([
            'uploaded_by' => $user->id,
            'source_type' => 'lkpm',
            'original_name' => 'LKPM.xlsx',
            'path' => 'imports/LKPM.xlsx',
            'drive_file_id' => 'drive-file',
            'drive_state' => 'source',
            'checksum' => str_repeat('d', 64),
        ]);
    }
}
