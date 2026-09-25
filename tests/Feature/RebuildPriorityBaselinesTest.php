<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\PriorityProjectBaseline;
use App\Models\Project;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RebuildPriorityBaselinesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_requires_force_and_rebuilds_only_baselines_without_deleting_snapshots(): void
    {
        $operator = User::factory()->create();
        $batch = ImportBatch::create([
            'uploaded_by' => $operator->id,
            'source_type' => 'lkpm',
            'original_name' => 'LKPM.xlsx',
            'path' => 'imports/LKPM.xlsx',
            'checksum' => str_repeat('c', 64),
        ]);
        $period = TargetPeriod::create([
            'year' => 2026,
            'quarter' => 'TW III',
            'target_amount' => 1000,
            'buffer_amount' => 1300,
            'is_active' => true,
        ]);
        $company = Company::create([
            'nib' => '1234567890001',
            'name' => 'PT Baseline Aman',
            'business_scale' => 'Usaha Menengah',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'project_code' => 'P-BASELINE',
            'planned_investment' => 5000,
        ]);

        foreach ([
            ['Triwulan IV', 2025, 100],
            ['Triwulan I', 2026, 400],
            ['Triwulan II', 2026, 900],
        ] as [$quarter, $year, $accumulated]) {
            LkpmReport::create([
                'import_batch_id' => $batch->id,
                'project_id' => $project->id,
                'project_code' => $project->project_code,
                'report_year' => $year,
                'report_quarter' => $quarter,
                'report_status' => 'Disetujui',
                'accumulated_investment' => $accumulated,
                'is_canonical' => true,
            ]);
        }

        $baseline = PriorityProjectBaseline::create([
            'target_period_id' => $period->id,
            'project_id' => $project->id,
            'initial_accumulated_investment' => 1,
            'historical_quarterly_realization' => 1,
            'captured_at' => now()->subDay(),
        ]);
        DailyPrioritySnapshot::create([
            'target_period_id' => $period->id,
            'snapshot_date' => today()->subDay(),
            'project_id' => $project->id,
            'company_id' => $company->id,
        ]);

        $this->artisan('priority:rebuild-baselines', ['--year' => 2026, '--quarter' => 'TW III'])
            ->expectsOutput('Gunakan --force setelah backup database dikonfirmasi.')
            ->assertFailed();
        $this->assertSame(1, (int) $baseline->fresh()->initial_accumulated_investment);

        $this->artisan('priority:rebuild-baselines', ['--year' => 2026, '--quarter' => 'TW III', '--force' => true])
            ->assertSuccessful();

        $rebuilt = PriorityProjectBaseline::firstOrFail();
        $this->assertSame(900, (int) $rebuilt->initial_accumulated_investment);
        $this->assertSame(400, (int) $rebuilt->historical_quarterly_realization);
        $this->assertSame(1, DailyPrioritySnapshot::count());
    }
}
