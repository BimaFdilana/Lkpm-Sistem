<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IntegratedDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_bagian_can_view_combined_project_and_lkpm_data(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000005', 'name' => 'PT Data Terpadu']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => '2026010001', 'planned_investment' => 1000000000]);
        $batch = ImportBatch::create(['uploaded_by' => $head->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('a', 64)]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'accumulated_investment' => 250000000, 'is_canonical' => true, 'source_payload' => ['TOTAL TAMBAHAN INVESTASI' => '1888444837']]);

        $this->actingAs($head)->get(route('integrated-data.index'))->assertOk()->assertSee('PT Data Terpadu')->assertSee('Triwulan III');
        $this->actingAs($head)->get(route('integrated-data.show', $project))->assertOk()->assertSee('Seluruh riwayat Laporan LKPM')->assertSee('250.000.000')->assertSee('Rp 1.888.444.837');
    }

    public function test_pic_cannot_view_integrated_data(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('integrated-data.index'))->assertForbidden();
    }

    public function test_kepala_bagian_can_view_the_top_assignment_candidates(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000006', 'name' => 'PT Kandidat Utama', 'business_scale' => 'Usaha Menengah']);
        Project::create(['company_id' => $company->id, 'project_code' => '2026010002', 'planned_investment' => 500000000]);

        \App\Models\TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 7900000000000, 'target_amount' => 2545000000000, 'buffer_amount' => 3200000000000, 'is_active' => true]);

        $this->actingAs($head)->get(route('assignments.candidates'))->assertOk()->assertSee('100 Kandidat Potensial')->assertSee('PT Kandidat Utama')->assertSee('Kandidat cadangan')->assertSee('Bagi rata kandidat ke PIC');
    }
}
