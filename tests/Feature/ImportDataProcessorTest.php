<?php

namespace Tests\Feature;

use App\ImportDataProcessor;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\ProjectCodeMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportDataProcessorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_project_import_creates_company_and_project_from_normalized_rows(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $batch = ImportBatch::create([
            'uploaded_by' => $user->id,
            'source_type' => 'projects',
            'original_name' => 'DP.Proyek.xlsx',
            'path' => 'imports/2026/DP.Proyek.xlsx',
            'checksum' => str_repeat('a', 64),
            'status' => 'uploaded',
        ]);
        $normalizedPath = 'imports/normalized/batch-'.$batch->id.'.jsonl';
        Storage::put($batch->path, 'source file');
        Storage::put($normalizedPath, json_encode([
            'nib' => '9120000000001',
            'company_name' => 'PT Bengkalis Maju',
            'investment_status' => 'PMDN',
            'business_scale' => 'Usaha Besar',
            'address' => 'Jalan Utama',
            'district' => 'Bengkalis',
            'subdistrict' => 'Bengkalis Kota',
            'project_code' => 'R-2026-001',
            'project_name' => 'Pabrik Baru',
            'kbli' => '10710',
            'kbli_description' => 'Industri Produk Makanan',
            'sector' => 'C. Industri Pengolahan',
            'project_stage' => 'Utama',
            'issued_at' => '2026-01-01',
            'planned_investment' => 15000000000,
            'planned_tki' => 35,
            'raw' => ['Id Proyek' => 'R-2026-001'],
        ])."\n");
        Process::fake(['*' => Process::result(json_encode(['accepted_rows' => 1, 'rejected_rows' => 0]))]);

        $summary = app(ImportDataProcessor::class)->process($batch);

        $this->assertSame(1, $summary['accepted_rows']);
        $this->assertSame(0, $summary['rejected_rows']);
        $this->assertSame(1, Company::count());
        $this->assertSame(1, Project::count());
        $this->assertSame('R-2026-001', Project::query()->sole()->project_code);
        $this->assertFalse(Storage::exists($normalizedPath));
        Process::assertRan(fn ($process): bool => str_contains(implode(' ', $process->command), 'python/import_processor.py'));
    }

    public function test_lkpm_import_updates_an_existing_logical_report_instead_of_creating_a_duplicate(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000002', 'name' => 'PT Laporan Tunggal']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => '2026010002']);
        $firstBatch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM-1.xlsx', 'path' => 'imports/LKPM-1.xlsx', 'checksum' => str_repeat('b', 64)]);
        $secondBatch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM-2.xlsx', 'path' => 'imports/LKPM-2.xlsx', 'checksum' => str_repeat('c', 64)]);
        $row = ['project_code' => $project->project_code, 'report_number' => 'L-2026-001', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'reported_at' => '2026-09-20', 'report_status' => 'Disetujui', 'total_investment_plan' => 1000, 'additional_investment' => 100, 'accumulated_investment' => 500, 'accumulated_fixed_capital' => 500, 'capital_explanation' => null, 'planned_tki' => 1, 'realized_tki' => 1, 'planned_tka' => 0, 'realized_tka' => 0, 'raw' => []];

        foreach ([$firstBatch, $secondBatch] as $batch) {
            Storage::put($batch->path, 'source file');
            Storage::put('imports/normalized/batch-'.$batch->id.'.jsonl', json_encode($row)."\n");
        }
        Process::fake(['*' => Process::result(json_encode(['accepted_rows' => 1, 'rejected_rows' => 0]))]);

        app(ImportDataProcessor::class)->process($firstBatch);
        app(ImportDataProcessor::class)->process($secondBatch);

        $this->assertDatabaseCount('lkpm_reports', 1);
        $this->assertDatabaseHas('lkpm_reports', ['project_code' => $project->project_code, 'report_number' => 'L-2026-001', 'import_batch_id' => $secondBatch->id, 'is_canonical' => true]);
    }

    public function test_project_import_relinks_existing_reports_with_the_same_normalized_code(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $lkpmBatch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('d', 64)]);
        $projectBatch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'projects', 'original_name' => 'DP.xlsx', 'path' => 'imports/DP.xlsx', 'checksum' => str_repeat('e', 64)]);
        LkpmReport::create(['import_batch_id' => $lkpmBatch->id, 'project_code' => '20260099', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'is_canonical' => true]);
        Storage::put($projectBatch->path, 'source file');
        Storage::put('imports/normalized/batch-'.$projectBatch->id.'.jsonl', json_encode([
            'nib' => '9120000000099', 'company_name' => 'PT Relink', 'investment_status' => 'PMDN', 'business_scale' => 'Usaha Menengah',
            'address' => null, 'district' => null, 'subdistrict' => null, 'project_code' => '20260099', 'project_name' => null,
            'kbli' => '10000', 'kbli_description' => null, 'sector' => 'Industri', 'project_stage' => null, 'issued_at' => null,
            'planned_investment' => 1000, 'planned_tki' => 1, 'raw' => [],
        ])."\n");
        Process::fake(['*' => Process::result(json_encode(['accepted_rows' => 1, 'rejected_rows' => 0]))]);

        app(ImportDataProcessor::class)->process($projectBatch);

        $this->assertNotNull(LkpmReport::query()->sole()->project_id);
    }

    public function test_new_lkpm_import_reuses_a_verified_manual_project_mapping(): void
    {
        Storage::fake();
        $user = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000100', 'name' => 'PT Mapping']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'DP-100']);
        ProjectCodeMapping::create(['lkpm_project_code' => 'LKPM-100', 'project_id' => $project->id, 'mapped_by' => $user->id]);
        $batch = ImportBatch::create(['uploaded_by' => $user->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('f', 64)]);
        $row = ['project_code' => 'LKPM-100', 'report_number' => 'M-100', 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'reported_at' => '2026-09-20', 'report_status' => 'Disetujui', 'total_investment_plan' => 1000, 'additional_investment' => 100, 'accumulated_investment' => 500, 'accumulated_fixed_capital' => 500, 'capital_explanation' => null, 'planned_tki' => 1, 'realized_tki' => 1, 'planned_tka' => 0, 'realized_tka' => 0, 'raw' => []];
        Storage::put($batch->path, 'source file');
        Storage::put('imports/normalized/batch-'.$batch->id.'.jsonl', json_encode($row)."\n");
        Process::fake(['*' => Process::result(json_encode(['accepted_rows' => 1, 'rejected_rows' => 0]))]);

        app(ImportDataProcessor::class)->process($batch);

        $this->assertSame($project->id, LkpmReport::query()->sole()->project_id);
    }
}
