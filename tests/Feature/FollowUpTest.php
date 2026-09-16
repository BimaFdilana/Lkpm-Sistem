<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_pic_can_create_follow_up(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000002', 'name' => 'PT Tindak Lanjut']);
        $assignment = Assignment::create(['company_id' => $company->id, 'pic_id' => $pic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($pic)
            ->post(route('assignments.follow-ups.store', $assignment), ['contact_status' => 'sudah_dihubungi', 'confirmation_status' => 'terkonfirmasi_sebagian', 'indicated_amount' => 250000000, 'note' => 'Perusahaan akan melapor.', 'next_follow_up_at' => '2026-10-05'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('follow_ups', ['assignment_id' => $assignment->id, 'created_by' => $pic->id, 'status' => 'sudah_dihubungi', 'confirmation_status' => 'terkonfirmasi_sebagian', 'indicated_amount' => 250000000]);
    }

    public function test_unassigned_pic_cannot_create_follow_up(): void
    {
        $assignedPic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000003', 'name' => 'PT Terbatas']);
        $assignment = Assignment::create(['company_id' => $company->id, 'pic_id' => $assignedPic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($otherPic)
            ->post(route('assignments.follow-ups.store', $assignment), ['contact_status' => 'sudah_dihubungi', 'confirmation_status' => 'belum_terkonfirmasi'])
            ->assertForbidden();
    }

    public function test_pic_can_record_operational_verification_without_changing_official_lkpm_data(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000004', 'name' => 'PT Verifikasi Operasional']);
        $assignment = Assignment::create(['company_id' => $company->id, 'pic_id' => $pic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'is_task_active' => true, 'assigned_at' => now()]);

        $this->actingAs($pic)
            ->post(route('assignments.follow-ups.store', $assignment), [
                'contact_status' => 'tidak_dapat_dihubungi',
                'confirmation_status' => 'belum_terkonfirmasi',
                'verification_status' => 'tidak_dapat_dihubungi',
                'note' => 'Nomor kantor belum tersambung; akan dicoba kembali.',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('follow_ups', [
            'assignment_id' => $assignment->id,
            'verification_status' => 'tidak_dapat_dihubungi',
        ]);
    }
}
