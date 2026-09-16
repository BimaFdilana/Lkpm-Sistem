<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssignmentDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pic_can_view_detail_of_own_assignment(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $assignment = $this->assignmentFor($pic);

        $this->actingAs($pic)
            ->get(route('assignments.show', $assignment))
            ->assertSee('PT Bengkalis Maju')
            ->assertSee('Kontak dan alamat perusahaan');
    }

    public function test_pic_cannot_view_detail_of_another_pic_assignment(): void
    {
        $assignedPic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $assignment = $this->assignmentFor($assignedPic);

        $this->actingAs($otherPic)->get(route('assignments.show', $assignment))->assertForbidden();
    }

    private function assignmentFor(User $pic): Assignment
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $company = Company::create(['nib' => '9120000000001', 'name' => 'PT Bengkalis Maju', 'address' => 'Jalan Utama']);

        return Assignment::create(['company_id' => $company->id, 'pic_id' => $pic->id, 'assigned_by' => $head->id, 'year' => 2026, 'quarter' => 'TW III', 'status' => 'active', 'assigned_at' => now()]);
    }
}
