<?php

namespace Tests\Feature;

use App\DailyPriorityEngine;
use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\TargetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DailyPriorityEngineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_an_immutable_project_snapshot_and_keeps_monitoring_out_of_valid_momentum(): void
    {
        $operator = User::factory()->create();
        $batch = ImportBatch::create(['uploaded_by' => $operator->id, 'source_type' => 'oss', 'original_name' => 'oss.xlsx', 'path' => 'imports/oss.xlsx', 'checksum' => str_repeat('a', 64)]);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1000, 'baseline_realization' => 0]);
        $company = Company::create(['nib' => '1234567890123', 'name' => 'PT Uji', 'business_scale' => 'Usaha Menengah']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'P-001', 'planned_investment' => 1000]);
        foreach ([['2025', 'Triwulan 1', 100], ['2025', 'Triwulan 2', 300], ['2026', 'Triwulan 2', 450], ['2026', 'Triwulan 3', 900]] as [$year, $quarter, $accumulated]) {
            LkpmReport::create([
                'import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code,
                'report_year' => $year, 'report_quarter' => $quarter, 'report_status' => $quarter === 'Triwulan 3' ? 'Diajukan' : 'Disetujui',
                'accumulated_investment' => $accumulated, 'is_canonical' => true,
            ]);
        }

        app(DailyPriorityEngine::class)->snapshot($period, Carbon::parse('2026-09-16'), $batch->id);
        $snapshot = DailyPrioritySnapshot::firstOrFail();

        $this->assertSame(450, $snapshot->baseline_accumulated_investment);
        $this->assertSame(0, $snapshot->valid_momentum);
        $this->assertSame(450, $snapshot->monitoring_momentum);
        $this->assertSame(175, $snapshot->projected_contribution);
        $this->assertSame('valid_history_median', $snapshot->projection_source);

        LkpmReport::query()->where('report_quarter', 'Triwulan 3')->update(['accumulated_investment' => 999]);
        app(DailyPriorityEngine::class)->snapshot($period, Carbon::parse('2026-09-16'), $batch->id);
        $this->assertSame(1, DailyPrioritySnapshot::count());
        $this->assertSame(900, (int) DailyPrioritySnapshot::firstOrFail()->monitoring_accumulated_investment);
    }

    public function test_historical_projection_uses_only_the_latest_approved_report_in_each_quarter(): void
    {
        $operator = User::factory()->create();
        $batch = ImportBatch::create(['uploaded_by' => $operator->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('b', 64)]);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW IV', 'target_amount' => 2000, 'buffer_amount' => 2600]);
        $company = Company::create(['nib' => '1234567890456', 'name' => 'PT Riwayat Valid', 'business_scale' => 'Usaha Menengah']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'P-HISTORY', 'planned_investment' => 3000]);

        foreach ([
            ['Triwulan I', '2026-03-10', 100],
            ['Triwulan II', '2026-06-10', 300],
            ['Triwulan II', '2026-06-15', 350],
            ['Triwulan III', '2026-09-15', 500],
        ] as [$quarter, $reportedAt, $accumulated]) {
            LkpmReport::create([
                'import_batch_id' => $batch->id,
                'project_id' => $project->id,
                'project_code' => $project->project_code,
                'report_year' => 2026,
                'report_quarter' => $quarter,
                'reported_at' => $reportedAt,
                'report_status' => 'Disetujui',
                'accumulated_investment' => $accumulated,
                'is_canonical' => true,
            ]);
        }

        app(DailyPriorityEngine::class)->snapshot($period, Carbon::parse('2026-10-01'), $batch->id);
        $snapshot = DailyPrioritySnapshot::firstOrFail();

        $this->assertSame(500, $snapshot->baseline_accumulated_investment);
        $this->assertSame(200, $snapshot->historical_quarterly_realization);
        $this->assertSame(200, $snapshot->projected_contribution);
    }
}
