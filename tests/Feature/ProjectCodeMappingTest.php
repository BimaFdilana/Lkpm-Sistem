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
        $report = LkpmReport::create(['import_batch_id' => $batch->id, 'project_code' => '202601-0001', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'is_canonical' => true, 'source_payload' => ['KONTAK NAMA' => 'Siti Aminah', 'KONTAK HP' => '081234567890', 'KONTAK EMAIL' => 'siti@example.test', 'JABATAN' => 'Direktur']]);

        $this->actingAs($head)
            ->post(route('reconciliations.store'), ['lkpm_project_code' => '202601-0001', 'project_code' => $project->project_code])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('project_code_mappings', ['lkpm_project_code' => '202601-0001', 'project_id' => $project->id, 'mapped_by' => $head->id]);
        $this->assertSame($project->id, $report->fresh()->project_id);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'contact_phone' => '+6281234567890', 'contact_email' => 'siti@example.test']);
    }

    public function test_pic_cannot_open_reconciliation_screen(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('reconciliations.index'))->assertForbidden();
    }

    public function test_kepala_bagian_can_filter_and_export_unlinked_codes_with_quality_metrics(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000006', 'name' => 'PT Kontak Kosong']);
        Project::create(['company_id' => $company->id, 'project_code' => 'PROJECT-WITHOUT-SECTOR']);
        $batch = ImportBatch::create(['uploaded_by' => $head->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('c', 64)]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_code' => 'UNLINKED-2026', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'is_canonical' => true]);
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_code' => 'UNLINKED-2025', 'report_year' => 2025, 'report_quarter' => 'Triwulan II', 'report_status' => 'Disetujui', 'is_canonical' => true]);

        $this->actingAs($head)
            ->get(route('reconciliations.index', ['year' => 2026, 'quarter' => 'Triwulan III', 'code' => '2026']))
            ->assertOk()
            ->assertSeeText('UNLINKED-2026')
            ->assertDontSeeText('UNLINKED-2025')
            ->assertSeeText('Email belum tersedia')
            ->assertSeeText('Sektor belum tersedia');

        $response = $this->actingAs($head)->get(route('reconciliations.export', ['year' => 2026]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('UNLINKED-2026', $response->streamedContent());
        $this->assertStringNotContainsString('UNLINKED-2025', $response->streamedContent());
    }

    public function test_pic_cannot_export_reconciliation_data(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('reconciliations.export'))->assertForbidden();
    }
}
