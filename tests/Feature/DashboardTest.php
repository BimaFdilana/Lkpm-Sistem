<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_renders_target_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'kepala_dinas']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 2545000000000]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Jadwal pengawasan aktif', 'Masa pelaporan OSS', 'Capaian target tahunan 2026', 'Capaian Periode Berjalan (TW III)'])
            ->assertSee('Edit target investasi tahunan')
            ->assertSee('bg-rose-50')
            ->assertSee('Belum ada TW aktif')
            ->assertSee('LKPM Disetujui TW I')
            ->assertSee('LKPM Disetujui TW IV')
            ->assertSee('Filter tahun')
            ->assertSee('Kinerja tindak lanjut PIC');
    }

    public function test_kadis_sees_active_quarter_progress_and_prior_shortfalls_without_double_counting(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW I', 'target_amount' => 1000]);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW II', 'target_amount' => 1500]);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 2000, 'annual_target' => 6000, 'is_active' => true]);
        $company = Company::create(['nib' => '9000000000025', 'name' => 'PT Uji Capaian', 'business_scale' => 'Usaha Menengah']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'CAPAIAN-25', 'planned_investment' => 10000]);
        $batch = ImportBatch::create(['uploaded_by' => $kadis->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('b', 64)]);

        foreach ([['Triwulan I', 600, 'Disetujui'], ['Triwulan II', 1000, 'Disetujui'], ['Triwulan III', 500, 'Disetujui'], ['Triwulan III', 1000, 'Perlu Perbaikan']] as [$quarter, $amount, $status]) {
            LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => 2026, 'report_quarter' => $quarter, 'report_status' => $status, 'additional_investment' => $amount, 'is_canonical' => true]);
        }

        $this->actingAs($kadis)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Capaian Periode Berjalan (TW III)')
            ->assertSeeText('25,0%')
            ->assertSeeText('Sisa target TW aktif')
            ->assertSeeText('Rp 1.500')
            ->assertSeeText('Sisa target TW sebelumnya yang tercatat')
            ->assertSeeText('Rp 400')
            ->assertSeeText('Rp 500')
            ->assertSeeText('tidak ditambahkan lagi ke target TW aktif');
    }

    public function test_kadis_sees_prior_approved_reports_even_when_prior_quarter_targets_are_missing(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 2545000000000, 'is_active' => true]);
        $batch = ImportBatch::create(['uploaded_by' => $kadis->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('c', 64)]);

        foreach ([['Triwulan I', 600000000000, 'Disetujui'], ['Triwulan II', 2200000000000, 'Disetujui'], ['Triwulan II', 90000000000, 'Draft'], ['Triwulan III', 500000000000, 'Disetujui']] as [$quarter, $amount, $status]) {
            LkpmReport::create(['import_batch_id' => $batch->id, 'project_code' => 'TEST-REPORT', 'report_year' => 2026, 'report_quarter' => $quarter, 'report_status' => $status, 'additional_investment' => $amount, 'is_canonical' => true]);
        }

        $this->actingAs($kadis)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Rp 2,55 T')
            ->assertSeeText('Rp 500,00 M')
            ->assertSeeText('Rp 2,05 T')
            ->assertSeeText('Realisasi LKPM TW sebelumnya tersedia; alokasi target per TW belum lengkap')
            ->assertSeeText('Rp 600,00 M')
            ->assertSeeText('Rp 2,20 T')
            ->assertSeeText('target TW belum tersimpan')
            ->assertDontSeeText('Rp 2,29 T')
            ->assertDontSeeText('Target TW sebelumnya belum tersimpan pada tahun ini');
    }

    public function test_kadis_sees_four_quarters_with_year_over_year_change_company_coverage_and_inactive_missing_quarter(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'is_active' => true]);
        $batch = ImportBatch::create(['uploaded_by' => $kadis->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('a', 64)]);
        $projects = [];
        foreach (range(1, 4) as $number) {
            $company = Company::create(['nib' => sprintf('90000000000%02d', $number), 'name' => 'PT Non UMK '.$number, 'business_scale' => 'Usaha Menengah']);
            $projects[] = Project::create(['company_id' => $company->id, 'project_code' => 'KADIS-'.$number, 'planned_investment' => 1000]);
        }
        $micro = Company::create(['nib' => '9000000000010', 'name' => 'PT Mikro', 'business_scale' => 'Usaha Mikro']);
        $microProject = Project::create(['company_id' => $micro->id, 'project_code' => 'KADIS-MIKRO', 'planned_investment' => 1000]);

        foreach ([[2025, 'Triwulan III', 0, 'Disetujui'], [2025, 'Triwulan III', 1, 'Disetujui'], [2026, 'Triwulan II', 3, 'Perlu Perbaikan'], [2026, 'Triwulan III', 0, 'Disetujui'], [2026, 'Triwulan III', 1, 'Disetujui'], [2026, 'Triwulan III', 2, 'Disetujui']] as [$year, $quarter, $projectIndex, $status]) {
            LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $projects[$projectIndex]->id, 'project_code' => $projects[$projectIndex]->project_code, 'report_year' => $year, 'report_quarter' => $quarter, 'report_status' => $status, 'is_canonical' => true]);
        }
        LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $microProject->id, 'project_code' => $microProject->project_code, 'report_year' => 2026, 'report_quarter' => 'Triwulan III', 'report_status' => 'Disetujui', 'is_canonical' => true]);

        $this->actingAs($kadis)->get(route('dashboard', ['lkpm_year' => 2026]))
            ->assertOk()
            ->assertSeeText('LKPM Disetujui TW III')
            ->assertSeeText('meningkat 100,0% dari 2025.')
            ->assertSeeText('dari 3 perusahaan unik')
            ->assertSeeText('Acuan: 4 proyek Non-UMK aktif')
            ->assertSeeText('3 dari 4 proyek sudah memiliki LKPM Disetujui (75,0%)')
            ->assertSeeText('3 dari 4 perusahaan non-UMK terdaftar (75,0%)')
            ->assertSeeText('LKPM Disetujui TW IV')
            ->assertSee('aria-label="LKPM TW IV 2026 belum tersedia"', false)
            ->assertSeeText('belum ada data TW IV. Kartu belum aktif.');

        $this->actingAs($kadis)->get(route('dashboard', ['lkpm_year' => 'all']))
            ->assertOk()
            ->assertSee('value="all" selected', false)
            ->assertSeeText('6 laporan')
            ->assertSeeText('Semua tahun tersedia')
            ->assertSeeText('proyek-tahun sudah memiliki LKPM Disetujui')
            ->assertSeeText('gabungan data lintas tahun; tidak dibandingkan dengan tahun sebelumnya.');
    }

    public function test_kabag_creates_snapshot_from_dashboard_and_views_combined_priority_tabs(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);
        $company = Company::create(['nib' => '9000000000024', 'name' => 'PT Uji Snapshot', 'business_scale' => 'Usaha Menengah']);
        Project::create(['company_id' => $company->id, 'project_code' => 'SNAP-24', 'planned_investment' => 1000]);

        $this->actingAs($head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buat Snapshot Hari Ini')
            ->assertDontSee('Buka prioritas harian');

        $this->actingAs($head)->post(route('priority.snapshot'))
            ->assertRedirect(route('priority.index'));

        $this->actingAs($head)->get(route('priority.index'))
            ->assertOk()
            ->assertSee('Prioritas Harian')
            ->assertSee('Snapshot Harian')
            ->assertDontSee('Buat snapshot hari ini');

        $this->actingAs($head)->get(route('priority.index', ['tab' => 'snapshots']))
            ->assertOk()
            ->assertSee('Riwayat snapshot prioritas')
            ->assertSee('Riwayat snapshot data impor')
            ->assertSee('Baseline historis per triwulan');

        $this->actingAs($head)->get(route('snapshots.index'))
            ->assertRedirect(route('priority.index', ['tab' => 'snapshots']));
    }
}
