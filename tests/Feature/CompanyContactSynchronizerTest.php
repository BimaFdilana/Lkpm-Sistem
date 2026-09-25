<?php

namespace Tests\Feature;

use App\CompanyContactSynchronizer;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CompanyContactSynchronizerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_syncs_all_contact_columns_from_linked_lkpm_report(): void
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000005', 'name' => 'PT Kontak Lengkap']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => '2026010001']);
        $batch = ImportBatch::create([
            'uploaded_by' => $user->id,
            'source_type' => 'lkpm',
            'original_name' => 'LKPM.xlsx',
            'path' => 'imports/LKPM.xlsx',
            'checksum' => str_repeat('a', 64),
        ]);
        $report = LkpmReport::create([
            'import_batch_id' => $batch->id,
            'project_id' => $project->id,
            'project_code' => $project->project_code,
            'report_year' => 2026,
            'report_quarter' => 'Triwulan III',
            'report_status' => 'Disetujui',
            'is_canonical' => true,
            'source_payload' => [
                'KONTAK NAMA' => 'Siti Aminah',
                'KONTAK HP' => '0812-3456-7890',
                'KONTAK EMAIL' => 'siti@example.test',
                'JABATAN' => 'Direktur',
            ],
        ]);

        $this->assertSame(1, app(CompanyContactSynchronizer::class)->sync());

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'contact_name' => 'Siti Aminah',
            'contact_phone' => '+6281234567890',
            'contact_email' => 'siti@example.test',
            'contact_position' => 'Direktur',
            'contact_source_report_id' => $report->id,
        ]);
        $this->assertNotNull($company->fresh()->contact_synced_at);
    }
}
