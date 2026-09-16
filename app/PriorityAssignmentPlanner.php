<?php

namespace App;

use App\Models\Company;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

class PriorityAssignmentPlanner
{
    /**
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, User>  $pics
     * @return Collection<int, array{company: Company, priority_rank: int, is_primary_target: bool, potential: int, pic_id: int}>
     */
    public function plan(Collection $companies, Collection $pics, TargetPeriod $period): Collection
    {
        $reportQuarter = str_replace('TW ', 'Triwulan ', $period->quarter);
        $ranked = $companies
            ->map(function (Company $company) use ($period, $reportQuarter): array {
                $activeReports = $company->projects
                    ->flatMap->reports
                    ->where('is_canonical', true)
                    ->where('report_year', $period->year)
                    ->where('report_quarter', $reportQuarter);
                $potential = $company->projects->sum(function ($project): int {
                    $latestReport = $project->reports
                        ->where('is_canonical', true)
                        ->sortByDesc(fn ($report): int => $report->reported_at?->getTimestamp() ?? 0)
                        ->first();

                    return max(0, (int) $project->planned_investment - (int) ($latestReport?->accumulated_investment ?? 0));
                });

                return [
                    'company' => $company,
                    'is_unreported' => ! $activeReports->contains('report_status', 'Disetujui'),
                    'potential' => $potential,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $unreportedComparison = $right['is_unreported'] <=> $left['is_unreported'];

                if ($unreportedComparison !== 0) {
                    return $unreportedComparison;
                }

                $potentialComparison = $right['potential'] <=> $left['potential'];

                return $potentialComparison !== 0
                    ? $potentialComparison
                    : mb_strtolower($left['company']->name) <=> mb_strtolower($right['company']->name);
            })
            ->values();

        $primaryCoverage = 0;
        $primaryTarget = (int) $period->buffer_amount;

        return $ranked->map(function (array $row, int $index) use ($pics, &$primaryCoverage, $primaryTarget): array {
            $picCount = $pics->count();
            $round = intdiv($index, $picCount);
            $position = $index % $picCount;
            $picIndex = $round % 2 === 0 ? $position : $picCount - 1 - $position;
            $isPrimaryTarget = $row['is_unreported'] && $primaryCoverage < $primaryTarget;

            if ($isPrimaryTarget) {
                $primaryCoverage += $row['potential'];
            }

            return [
                'company' => $row['company'],
                'priority_rank' => $index + 1,
                'is_primary_target' => $isPrimaryTarget,
                'potential' => $row['potential'],
                'pic_id' => $pics->values()->get($picIndex)->id,
            ];
        });
    }
}
