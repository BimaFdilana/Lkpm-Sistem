<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_dinas_does_not_see_operational_assignment_menu(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);

        $this->actingAs($kadis)
            ->get(route('dashboard'))
            ->assertSee('Ringkasan eksekutif investasi')
            ->assertDontSee('Assignment PIC');
    }

    public function test_pic_only_sees_own_task_navigation(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)
            ->get(route('dashboard'))
            ->assertRedirect(route('assignments.index'));

        $this->actingAs($pic)
            ->get(route('assignments.index'))
            ->assertSee('Tugas Saya')
            ->assertDontSee('Seluruh perusahaan menurut prioritas')
            ->assertDontSee('Navigasi utama')
            ->assertDontSee('Buka navigasi')
            ->assertDontSee('>Dashboard<', false)
            ->assertDontSee('Impor Data');
    }

    public function test_kepala_dinas_cannot_open_assignment_screen(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);

        $this->actingAs($kadis)->get(route('assignments.index'))->assertForbidden();
    }

    public function test_pic_cannot_open_or_update_another_pics_assignment(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9990000000001', 'name' => 'PT Milik PIC Lain']);
        $assignment = Assignment::create(['company_id' => $company->id, 'pic_id' => $otherPic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'is_task_active' => true, 'assigned_at' => now()]);

        $this->actingAs($pic)->get(route('assignments.show', $assignment))->assertForbidden();
        $this->actingAs($pic)->post(route('assignments.follow-ups.store', $assignment), [
            'contact_status' => 'sudah_dihubungi',
            'confirmation_status' => 'belum_terkonfirmasi',
        ])->assertForbidden();
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_operational_and_executive_mutations_are_separated_by_role(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        $head = User::factory()->create(['role' => 'kepala_bagian']);

        $this->actingAs($head)->post(route('dashboard.annual-target.store'))->assertForbidden();
        $this->actingAs($kadis)->post(route('assignments.candidates.assign'))->assertForbidden();
        $this->actingAs($kadis)->post(route('periods.store'))->assertForbidden();
    }
}
