<?php

namespace Tests\Feature;

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
            ->assertSee('Tugas tindak lanjut perusahaan')
            ->assertSee('Tugas Saya')
            ->assertDontSee('Impor Data');
    }

    public function test_kepala_dinas_cannot_open_assignment_screen(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);

        $this->actingAs($kadis)->get(route('assignments.index'))->assertForbidden();
    }
}
