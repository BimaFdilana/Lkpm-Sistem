<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\TargetPeriod;
use App\Models\User;
use App\PriorityAssignmentPlanner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PriorityAssignmentPlannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_distributes_company_counts_evenly_with_the_snake_method(): void
    {
        $period = TargetPeriod::create(['year' => 2026, 'quarter' => 'TW III', 'annual_target' => 7900000000000, 'target_amount' => 2545000000000, 'buffer_amount' => 3200000000000, 'baseline_realization' => 2810000000000]);
        $pics = User::factory()->count(4)->sequence(
            ['name' => 'PIC 1', 'role' => 'pic'],
            ['name' => 'PIC 2', 'role' => 'pic'],
            ['name' => 'PIC 3', 'role' => 'pic'],
            ['name' => 'PIC 4', 'role' => 'pic'],
        )->create();
        $companies = collect(range(1, 9))->map(function (int $number): Company {
            $company = Company::create(['nib' => '9000000000'.$number, 'name' => 'Perusahaan '.$number]);
            Project::create(['company_id' => $company->id, 'project_code' => 'PROYEK-'.$number, 'planned_investment' => (10 - $number) * 100000000]);

            return $company->load('projects.reports');
        });

        $plan = app(PriorityAssignmentPlanner::class)->plan($companies, $pics, $period);

        $this->assertSame([3, 2, 2, 2], $plan->countBy('pic_id')->sortKeys()->values()->all());
        $this->assertSame([$pics[0]->id, $pics[1]->id, $pics[2]->id, $pics[3]->id, $pics[3]->id, $pics[2]->id, $pics[1]->id, $pics[0]->id], $plan->take(8)->pluck('pic_id')->all());
        $this->assertTrue($plan->take(8)->every(fn (array $row): bool => $row['is_primary_target']));
    }
}
