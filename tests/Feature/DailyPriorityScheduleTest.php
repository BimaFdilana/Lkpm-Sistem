<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\Project;
use App\Models\Setting;
use App\Models\TargetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DailyPriorityScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_snapshot_command_uses_the_latest_ready_lkpm_batch(): void
    {
        Carbon::setTestNow('2026-09-24 08:20:00');
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $batch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('e', 64), 'status' => 'ready']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);
        $company = Company::create(['nib' => '9000000000099', 'name' => 'PT Snapshot Terjadwal', 'business_scale' => 'Usaha Menengah']);
        Project::create(['company_id' => $company->id, 'project_code' => 'SCHEDULED-1', 'planned_investment' => 1000]);

        $this->artisan('priority:snapshot', ['--scheduled' => true])->assertSuccessful();

        $snapshot = DailyPrioritySnapshot::firstOrFail();
        $this->assertSame($batch->id, $snapshot->import_batch_id);
        $this->assertSame('2026-09-24', $snapshot->snapshot_date->toDateString());
    }

    public function test_snapshot_is_deferred_while_an_import_is_processing(): void
    {
        Carbon::setTestNow('2026-09-24 08:20:00');
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('f', 64), 'status' => 'processing']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);

        $this->artisan('priority:snapshot', ['--scheduled' => true])
            ->expectsOutput('Snapshot ditunda karena masih ada impor yang sedang diproses.')
            ->assertSuccessful();

        $this->assertDatabaseCount('daily_priority_snapshots', 0);
    }

    public function test_scheduled_snapshot_waits_until_the_configured_time(): void
    {
        Carbon::setTestNow('2026-09-24 07:50:00');
        config()->set('lkpm.priority_snapshot_time', '08:15');
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);

        $this->artisan('priority:snapshot', ['--scheduled' => true])->assertSuccessful();

        $this->assertDatabaseCount('daily_priority_snapshots', 0);
    }

    public function test_saved_operational_time_overrides_the_environment_default(): void
    {
        Carbon::setTestNow('2026-09-24 08:20:00');
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);
        Setting::create(['key' => 'priority.snapshot_time', 'value' => '09:00', 'updated_by' => $head->id]);

        $this->artisan('priority:snapshot', ['--scheduled' => true])->assertSuccessful();

        $this->assertDatabaseCount('daily_priority_snapshots', 0);
    }
}
