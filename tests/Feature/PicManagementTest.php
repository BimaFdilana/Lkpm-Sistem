<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PicManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_bagian_can_create_pic_without_kadis_approval(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);

        $this->actingAs($head)
            ->post(route('pics.store'), ['name' => 'PIC Baru', 'email' => 'pic.baru@example.test', 'password' => 'kata-sandi-aman', 'password_confirmation' => 'kata-sandi-aman'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['name' => 'PIC Baru', 'email' => 'pic.baru@example.test', 'role' => 'pic']);
    }

    public function test_kepala_bagian_can_change_assignment_pic(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $firstPic = User::factory()->create(['role' => 'pic']);
        $secondPic = User::factory()->create(['role' => 'pic']);
        $company = Company::create(['nib' => '9120000000004', 'name' => 'PT Pindah PIC']);
        $assignment = Assignment::create(['company_id' => $company->id, 'pic_id' => $firstPic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($head)
            ->put(route('assignments.pic.update', $assignment), ['pic_id' => $secondPic->id])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'pic_id' => $secondPic->id]);
    }
}
