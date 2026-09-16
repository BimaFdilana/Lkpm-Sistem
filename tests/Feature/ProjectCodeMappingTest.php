<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProjectCodeMappingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_bagian_can_map_lkpm_code_to_project_and_link_reports(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000005', 'name' => 'PT Rekonsiliasi']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'R-2026-REKON-001']);
        $batch = ImportBatch::create(['uploaded_by' => $head->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('b', 64)]);
        $report = LkpmReport::create(['import_batch_id' => $batch->id, 'project_code' => '202601-0001', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui']);

        $this->actingAs($head)
            ->post(route('reconciliations.store'), ['lkpm_project_code' => '202601-0001', 'project_code' => $project->project_code])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('project_code_mappings', ['lkpm_project_code' => '202601-0001', 'project_id' => $project->id, 'mapped_by' => $head->id]);
        $this->assertSame($project->id, $report->fresh()->project_id);
    }

    public function test_pic_cannot_open_reconciliation_screen(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('reconciliations.index'))->assertForbidden();
    }
}
