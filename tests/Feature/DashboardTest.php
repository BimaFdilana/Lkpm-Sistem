<?php

namespace Tests\Feature;

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

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Rp 2,545 T');
    }
}
