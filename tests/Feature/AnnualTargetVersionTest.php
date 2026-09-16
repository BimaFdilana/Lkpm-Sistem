<?php

namespace Tests\Feature;

use App\Models\AnnualTargetVersion;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnnualTargetVersionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kadis_versions_future_quarters_without_rewriting_the_active_period(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        $active = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 1000, 'target_amount' => 300, 'is_active' => true]);
        $future = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW IV', 'annual_target' => 1000, 'target_amount' => 250]);

        $this->actingAs($kadis)->post(route('annual-targets.store'), ['year' => 2026, 'annual_target' => 1200, 'tw_1' => 250, 'tw_2' => 250, 'tw_3' => 300, 'tw_4' => 400, 'reason' => 'Penyesuaian keputusan investasi tahunan.'])->assertRedirect(route('annual-targets.index', ['year' => 2026]));

        $this->assertSame(1, AnnualTargetVersion::count());
        $this->assertSame(300, $active->fresh()->target_amount);
        $this->assertSame(400, $future->fresh()->target_amount);
        $this->assertNotNull($future->fresh()->annual_target_version_id);
    }

    public function test_distribution_must_match_the_annual_target(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        $this->actingAs($kadis)->from(route('annual-targets.index'))->post(route('annual-targets.store'), ['year' => 2026, 'annual_target' => 1000, 'tw_1' => 100, 'tw_2' => 100, 'tw_3' => 100, 'tw_4' => 100, 'reason' => 'Penyesuaian keputusan investasi tahunan.'])->assertSessionHasErrors('annual_target');
    }
}
