<?php

namespace Tests\Feature;

use App\DailySnapshotRecorder;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\User;
use App\QuarterlyBaselineBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuarterlyBaselineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_builds_a_historical_baseline_from_the_previous_approved_quarter(): void
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000010', 'name' => 'PT Baseline']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => '2025010001']);
        $batch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('a', 64)]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => 2025, 'report_quarter' => 'Triwulan IV', 'report_status' => 'Disetujui', 'accumulated_investment' => 100000000, 'is_canonical' => true]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => 2026, 'report_quarter' => 'Triwulan I', 'report_status' => 'Disetujui', 'accumulated_investment' => 250000000, 'is_canonical' => true]);

        app(QuarterlyBaselineBuilder::class)->build();

        $this->assertDatabaseHas('quarterly_project_baselines', ['project_id' => $project->id, 'year' => 2025, 'quarter' => 'Triwulan IV', 'baseline_amount' => 0, 'baseline_is_estimated' => 1]);
        $this->assertDatabaseHas('quarterly_project_baselines', ['project_id' => $project->id, 'year' => 2026, 'quarter' => 'Triwulan I', 'baseline_amount' => 100000000, 'ending_amount' => 250000000, 'momentum_amount' => 150000000, 'baseline_is_estimated' => 0]);
    }

    public function test_it_records_a_daily_snapshot_from_an_import_batch(): void
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000011', 'name' => 'PT Snapshot']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => '2026010001']);
        $batch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('b', 64)]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'accumulated_investment' => 300000000, 'is_canonical' => true]);

        $this->assertSame(1, app(DailySnapshotRecorder::class)->record($batch));
        $this->assertDatabaseHas('daily_project_snapshots', ['project_id' => $project->id, 'year' => 2026, 'quarter' => 'Triwulan III', 'accumulated_investment' => 300000000, 'is_valid_realization' => 1]);
    }
}
