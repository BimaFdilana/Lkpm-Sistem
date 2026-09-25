<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\FollowUp;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pic_tasks_and_history_are_separated_by_own_follow_up_records(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'is_active' => true]);

        foreach (['PT Belum Dihubungi', 'PT Sudah Dicatat', 'PT Catatan PIC Lama'] as $index => $name) {
            $company = Company::create(['nib' => '90000000010'.$index, 'name' => $name]);
            $assignments[] = Assignment::create([
                'company_id' => $company->id,
                'pic_id' => $pic->id,
                'assigned_by' => $head->id,
                'year' => 2026,
                'quarter' => 'TW III',
                'status' => 'active',
                'is_task_active' => true,
                'assigned_at' => now(),
            ]);
        }

        FollowUp::create(['assignment_id' => $assignments[1]->id, 'created_by' => $pic->id, 'status' => 'sudah_dihubungi', 'contact_status' => 'sudah_dihubungi', 'confirmation_status' => 'belum_terkonfirmasi', 'note' => 'Akan dihubungi kembali.']);
        FollowUp::create(['assignment_id' => $assignments[2]->id, 'created_by' => $otherPic->id, 'status' => 'sudah_dihubungi', 'contact_status' => 'sudah_dihubungi', 'confirmation_status' => 'belum_terkonfirmasi']);

        $this->actingAs($pic)->get(route('assignments.index'))
            ->assertOk()
            ->assertSeeText('PT Belum Dihubungi')
            ->assertSeeText('PT Catatan PIC Lama')
            ->assertDontSeeText('PT Sudah Dicatat')
            ->assertSeeText('Riwayat Tindak Lanjut');

        $this->actingAs($pic)->get(route('assignments.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSeeText('PT Sudah Dicatat')
            ->assertSeeText('Akan dihubungi kembali.')
            ->assertDontSeeText('PT Belum Dihubungi')
            ->assertDontSeeText('PT Catatan PIC Lama');
    }

    public function test_kepala_bagian_distributes_snapshot_targets_evenly_to_two_pics(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $firstPic = User::factory()->create(['role' => 'pic']);
        $secondPic = User::factory()->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 7900000000000, 'target_amount' => 2545000000000, 'buffer_amount' => 3200000000000, 'baseline_realization' => 2810000000000, 'is_active' => true]);
        foreach (range(1, 4) as $number) {
            $company = Company::create(['nib' => '90000000000'.$number, 'name' => 'Perusahaan '.$number, 'business_scale' => 'Usaha Besar']);
            $project = Project::create(['company_id' => $company->id, 'project_code' => 'P-'.$number, 'planned_investment' => 1000000000]);
            DailyPrioritySnapshot::create([
                'target_period_id' => $period->id,
                'snapshot_date' => '2026-09-16',
                'project_id' => $project->id,
                'company_id' => $company->id,
                'planned_investment' => 1000000000,
                'remaining_potential' => 900000000,
                'projected_contribution' => 100000000 * $number,
                'projection_source' => 'valid_history_median',
                'candidate_tier' => $number <= 2 ? 'hijau' : 'cadangan',
                'priority_rank' => $number,
            ]);
        }

        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertStatus(302)->assertSessionHas('status');
        $this->assertDatabaseCount('assignments', 4);
        $this->assertSame(2, $firstPic->assignments()->count());
        $this->assertSame(2, $secondPic->assignments()->count());
        $this->assertSame(1, $firstPic->assignments()->where('queue_type', 'primary')->count());
        $this->assertSame(1, $secondPic->assignments()->where('queue_type', 'primary')->count());
        $this->assertSame(1, $firstPic->assignments()->where('queue_type', 'reserve')->count());
        $this->assertSame(1, $secondPic->assignments()->where('queue_type', 'reserve')->count());
        $this->assertSame(0, $firstPic->assignments()->where('queue_type', 'reserve')->where('is_task_active', true)->count());
        $this->assertSame(0, $secondPic->assignments()->where('queue_type', 'reserve')->where('is_task_active', true)->count());
        $this->assertDatabaseHas('assignments', ['priority_rank' => 1, 'queue_type' => 'primary', 'is_task_active' => true]);
    }

    public function test_risk_confirmation_activates_one_standby_reserve_for_each_pic(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $pics = User::factory()->count(2)->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);

        foreach (range(1, 4) as $number) {
            $company = Company::create(['nib' => '930000000000'.$number, 'name' => 'Perusahaan Buffer '.$number, 'business_scale' => 'Usaha Besar']);
            $project = Project::create(['company_id' => $company->id, 'project_code' => 'BUFFER-'.$number, 'planned_investment' => 1000]);
            DailyPrioritySnapshot::create([
                'target_period_id' => $period->id,
                'snapshot_date' => '2026-09-24',
                'project_id' => $project->id,
                'company_id' => $company->id,
                'remaining_potential' => 1000,
                'projected_contribution' => 500,
                'candidate_tier' => $number <= 2 ? 'hijau' : 'cadangan',
            ]);
        }

        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertRedirect(route('assignments.index'));
        $this->assertSame(2, Assignment::query()->where('queue_type', 'reserve')->where('is_task_active', false)->count());

        $primary = Assignment::query()->where('queue_type', 'primary')->firstOrFail();
        $this->actingAs($primary->pic)->post(route('assignments.follow-ups.store', $primary), [
            'contact_status' => 'sudah_dihubungi',
            'confirmation_status' => 'terkonfirmasi_sebagian',
            'indicated_amount' => 100,
        ])->assertSessionHas('status');

        $this->assertSame(2, Assignment::query()->where('queue_type', 'reserve')->where('is_task_active', true)->count());
        foreach ($pics as $pic) {
            $this->assertSame(1, $pic->assignments()->where('queue_type', 'reserve')->where('is_task_active', true)->count());
        }
    }

    public function test_kepala_bagian_can_assign_amber_companies_for_initial_verification_without_target_projection(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $firstPic = User::factory()->create(['role' => 'pic']);
        $secondPic = User::factory()->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'buffer_amount' => 1300, 'is_active' => true]);

        foreach (range(1, 4) as $number) {
            $company = Company::create(['nib' => '80000000000'.$number, 'name' => 'Perusahaan Amber '.$number, 'business_scale' => 'Usaha Besar']);
            $project = Project::create(['company_id' => $company->id, 'project_code' => 'A-'.$number, 'planned_investment' => 1000]);
            DailyPrioritySnapshot::create([
                'target_period_id' => $period->id,
                'snapshot_date' => '2026-09-23',
                'project_id' => $project->id,
                'company_id' => $company->id,
                'planned_investment' => 1000,
                'remaining_potential' => 100 * $number,
                'candidate_tier' => 'amber',
            ]);
        }

        $this->actingAs($head)->post(route('assignments.candidates.assign-verification'))->assertRedirect(route('assignments.index'));

        $this->assertDatabaseCount('assignments', 4);
        $this->assertSame(2, $firstPic->assignments()->count());
        $this->assertSame(2, $secondPic->assignments()->count());
        $this->assertDatabaseHas('assignments', ['queue_type' => 'verification', 'is_task_active' => true, 'is_primary_target' => false, 'potential_at_assignment' => 0]);
    }

    public function test_initial_assignment_is_capped_at_one_hundred_and_refills_the_same_pic_once_per_completed_company(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $pics = User::factory()->count(4)->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 100000, 'buffer_amount' => 130000, 'is_active' => true]);

        foreach (range(1, 102) as $number) {
            $company = Company::create(['nib' => sprintf('990000%07d', $number), 'name' => 'Perusahaan '.$number, 'business_scale' => 'Usaha Besar']);
            $project = Project::create(['company_id' => $company->id, 'project_code' => 'Q-'.$number, 'planned_investment' => 1000]);
            DailyPrioritySnapshot::create([
                'target_period_id' => $period->id,
                'snapshot_date' => '2026-09-23',
                'project_id' => $project->id,
                'company_id' => $company->id,
                'planned_investment' => 1000,
                'remaining_potential' => 1000,
                'projected_contribution' => 1000 - $number,
                'candidate_tier' => 'hijau',
            ]);
        }

        $this->actingAs($head)->get(route('assignments.candidates'))->assertOk()->assertSeeText('102 perusahaan');
        $this->actingAs($head)->get(route('assignments.index', ['candidate_page' => 3]))
            ->assertOk()
            ->assertSeeText('Perusahaan 101')
            ->assertSeeText('Perusahaan 102');
        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertRedirect(route('assignments.index'));
        $this->assertSame(100, Assignment::query()->count());
        foreach ($pics as $pic) {
            $this->assertSame(25, $pic->assignments()->count());
        }

        $completed = Assignment::query()->where('priority_rank', 1)->firstOrFail();
        $pic = $completed->pic;
        $this->actingAs($pic)->post(route('assignments.follow-ups.store', $completed), [
            'contact_status' => 'sudah_dihubungi',
            'confirmation_status' => 'belum_terkonfirmasi',
        ])->assertSessionHas('status');

        $this->assertSame(101, Assignment::query()->count());
        $this->assertSame(26, $pic->assignments()->count());
        $this->assertDatabaseHas('assignments', ['priority_rank' => 101, 'pic_id' => $pic->id]);
        $this->actingAs($pic)->get(route('assignments.index'))->assertDontSee('>'.$completed->company->name.'</p>', false);
        $this->actingAs($pic)->get(route('assignments.index', ['tab' => 'history']))->assertSeeText($completed->company->name);

        $this->actingAs($pic)->post(route('assignments.follow-ups.store', $completed), [
            'contact_status' => 'sudah_dihubungi',
            'confirmation_status' => 'belum_terkonfirmasi',
        ])->assertSessionHas('status');
        $this->assertSame(101, Assignment::query()->count());

        $period->update(['is_active' => false]);
        $nextPeriod = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW IV', 'target_amount' => 100000, 'is_active' => true]);
        DailyPrioritySnapshot::create([
            'target_period_id' => $nextPeriod->id,
            'snapshot_date' => '2026-12-23',
            'project_id' => $completed->company->projects->first()->id,
            'company_id' => $completed->company_id,
            'remaining_potential' => 1000,
            'projected_contribution' => 500,
            'candidate_tier' => 'hijau',
        ]);
        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertRedirect(route('assignments.index'));
        $this->assertDatabaseHas('assignments', ['company_id' => $completed->company_id, 'quarter' => 'TW IV', 'status' => 'active']);
    }

    public function test_approved_reporting_history_ranks_active_company_above_larger_unverified_gap(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $pic = User::factory()->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'is_active' => true]);
        $batch = ImportBatch::create(['uploaded_by' => $head->id, 'source_type' => 'lkpm', 'original_name' => 'LKPM.xlsx', 'path' => 'imports/LKPM.xlsx', 'checksum' => str_repeat('a', 64)]);

        foreach ([['Aktif', 100], ['Gap Besar', 900]] as [$name, $projection]) {
            $company = Company::create(['nib' => '910000000000'.($name === 'Aktif' ? '1' : '2'), 'name' => 'PT '.$name, 'business_scale' => 'Usaha Besar']);
            $project = Project::create(['company_id' => $company->id, 'project_code' => 'P-'.$name, 'planned_investment' => 2000]);
            DailyPrioritySnapshot::create(['target_period_id' => $period->id, 'snapshot_date' => '2026-09-23', 'project_id' => $project->id, 'company_id' => $company->id, 'remaining_potential' => 1000, 'projected_contribution' => $projection, 'candidate_tier' => 'hijau']);
            if ($name === 'Aktif') {
                foreach ([['2025', 'Triwulan IV'], ['2026', 'Triwulan I'], ['2026', 'Triwulan II']] as [$year, $quarter]) {
                    LkpmReport::create(['import_batch_id' => $batch->id, 'project_id' => $project->id, 'project_code' => $project->project_code, 'report_year' => $year, 'report_quarter' => $quarter, 'report_status' => 'Disetujui', 'is_canonical' => true]);
                }
            }
        }

        $this->actingAs($head)->get(route('assignments.candidates'))->assertOk()->assertSeeText('3/4 TW sebelumnya');
        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertRedirect(route('assignments.index'));
        $this->assertSame('PT Aktif', Assignment::query()->where('priority_rank', 1)->firstOrFail()->company->name);
        $this->assertSame(2, $pic->assignments()->count());
    }

    public function test_company_already_approved_in_current_quarter_stays_visible_but_is_not_assigned(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        User::factory()->create(['role' => 'pic']);
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'target_amount' => 1000, 'is_active' => true]);
        $company = Company::create(['nib' => '9200000000001', 'name' => 'PT Sudah Lapor', 'business_scale' => 'Usaha Besar']);
        $project = Project::create(['company_id' => $company->id, 'project_code' => 'CURRENT-1', 'planned_investment' => 2000]);
        DailyPrioritySnapshot::create([
            'target_period_id' => $period->id,
            'snapshot_date' => '2026-09-23',
            'project_id' => $project->id,
            'company_id' => $company->id,
            'remaining_potential' => 1000,
            'projected_contribution' => 0,
            'candidate_tier' => 'monitoring',
            'verification_status' => 'lkpm_disetujui',
        ]);

        $this->actingAs($head)->get(route('assignments.candidates'))->assertOk()->assertSeeText('PT Sudah Lapor')->assertSeeText('Sudah lapor/selesai atau tanpa sisa');
        $this->actingAs($head)->post(route('assignments.candidates.assign'))->assertStatus(422);
        $this->assertDatabaseCount('assignments', 0);
    }
}
