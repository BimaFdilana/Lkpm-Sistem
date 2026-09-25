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

    public function test_kadis_can_update_annual_target_from_dashboard_without_rewriting_active_period(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        $active = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 1000, 'target_amount' => 300, 'is_active' => true]);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW IV', 'annual_target' => 1000, 'target_amount' => 250]);
        AnnualTargetVersion::create(['year' => 2026, 'annual_target' => 1000, 'quarter_distribution' => ['TW I' => 200, 'TW II' => 250, 'TW III' => 300, 'TW IV' => 250], 'reason' => 'Target awal untuk distribusi tahunan.', 'effective_at' => now(), 'created_by' => $kadis->id]);

        $this->actingAs($kadis)
            ->post(route('dashboard.annual-target.store'), [
                'year' => 2026,
                'annual_target' => 1200,
                'tw_1' => 240,
                'tw_2' => 300,
                'tw_3' => 360,
                'tw_4' => 300,
            ])
            ->assertRedirect(route('dashboard'));

        $latest = AnnualTargetVersion::query()->latest('id')->firstOrFail();
        $this->assertSame(1200, $latest->annual_target);
        $this->assertSame(1200, array_sum($latest->quarter_distribution));
        $this->assertSame(300, $latest->quarter_distribution['TW IV']);
        $this->assertSame(300, $active->fresh()->target_amount);
    }

    public function test_dashboard_target_update_requires_a_complete_official_quarter_distribution(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 7900, 'target_amount' => 2545, 'is_active' => true]);

        $this->actingAs($kadis)
            ->from(route('dashboard'))
            ->post(route('dashboard.annual-target.store'), [
                'year' => 2026,
                'annual_target' => 7900,
                'tw_3' => 2545,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors(['tw_1', 'tw_2', 'tw_4']);

        $this->assertDatabaseCount('annual_target_versions', 0);
    }

    public function test_dashboard_target_update_rejects_a_distribution_that_does_not_equal_the_annual_target(): void
    {
        $kadis = User::factory()->create(['role' => 'kepala_dinas']);

        $this->actingAs($kadis)
            ->from(route('dashboard'))
            ->post(route('dashboard.annual-target.store'), [
                'year' => 2026,
                'annual_target' => 7900,
                'tw_1' => 1000,
                'tw_2' => 1000,
                'tw_3' => 2545,
                'tw_4' => 1000,
            ])
            ->assertSessionHasErrors('annual_target');

        $this->assertDatabaseCount('annual_target_versions', 0);
    }
}
