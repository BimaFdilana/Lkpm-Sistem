<?php

namespace Tests\Feature;

use App\ImportDataProcessor;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Project;
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
}
