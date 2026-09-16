<?php

namespace App;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

class AssignmentAllocator
{
    /** @return array<int, array<int, Company>> */
    public function allocate(Collection $companies, Collection $pics): array
    {
        $assignments = $pics->mapWithKeys(fn (User $pic): array => [$pic->id => []])->all();

        foreach ($companies->sortByDesc('planned_investment')->values() as $company) {
            $picId = collect($assignments)
                ->sortBy(fn (array $companies): array => [count($companies), collect($companies)->sum('planned_investment')])
                ->keys()
                ->first();

            $assignments[$picId][] = $company;
        }

        return $assignments;
    }
}
