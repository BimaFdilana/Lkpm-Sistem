<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_bagian_distributes_four_companies_evenly_to_two_pics(): void
    {
        $head = User::factory()->create(['role' => 'kepala_bagian']);
        $firstPic = User::factory()->create(['role' => 'pic']);
        $secondPic = User::factory()->create(['role' => 'pic']);
        TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 7900000000000, 'target_amount' => 2545000000000, 'buffer_amount' => 3200000000000, 'baseline_realization' => 2810000000000, 'is_active' => true]);
        foreach (range(1, 4) as $number) {
            Company::create(['nib' => '90000000000'.$number, 'name' => 'Perusahaan '.$number, 'business_scale' => 'Usaha Besar']);
        }

        $this->actingAs($head)->post(route('assignments.rebalance'))->assertSessionHas('status');
        $this->assertDatabaseCount('assignments', 4);
        $this->assertSame(2, $firstPic->assignments()->count());
        $this->assertSame(2, $secondPic->assignments()->count());
    }
}
