<?php

namespace Tests\Feature;

use App\GoogleDriveStorage;
use App\Models\ImportBatch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ImportBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pic_receives_forbidden_response_for_import_screen(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('imports.index'))->assertForbidden();
    }

    public function test_kepala_bagian_can_update_the_daily_snapshot_time(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);

        $this->actingAs($head)
            ->post(route('imports.snapshot-time.update'), ['priority_snapshot_time' => '08:30'])
            ->assertSessionHas('status');

        $this->assertSame('08:30', Setting::query()->where('key', 'priority.snapshot_time')->firstOrFail()->value);
    }

    public function test_kepala_bagian_can_retry_an_archive_move_after_a_successful_import(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $batch = ImportBatch::create([
            'uploaded_by' => $head->id,
            'source_type' => 'lkpm',
            'original_name' => 'LKPM.xlsx',
            'path' => 'imports/LKPM.xlsx',
            'drive_file_id' => 'drive-file',
            'drive_state' => 'archive_pending',
            'checksum' => str_repeat('e', 64),
            'status' => 'ready',
        ]);
        $drive = $this->mock(GoogleDriveStorage::class);
        $drive->shouldReceive('archive')->once()->withArgs(fn (ImportBatch $value): bool => $value->is($batch));

        $this->actingAs($head)
            ->post(route('imports.retry-drive-move', $batch))
            ->assertSessionHas('status');

        $batch->refresh();
        $this->assertSame('archived', $batch->drive_state);
        $this->assertNull($batch->drive_error);
        $this->assertNotNull($batch->drive_moved_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'import_drive_move_retried',
            'auditable_type' => ImportBatch::class,
            'auditable_id' => $batch->id,
        ]);
    }
}
