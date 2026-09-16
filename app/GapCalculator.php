<?php

namespace App;

use App\Models\LkpmReport;
use App\Models\Project;
use Illuminate\Support\Collection;

class GapCalculator
{
    public function projectPotential(Project $project, ?LkpmReport $report): int
    {
        return max(0, (int) ($report?->total_investment_plan ?: $project->planned_investment) - (int) ($report?->accumulated_investment ?? 0));
    }

    /** @param Collection<int, LkpmReport> $reports */
    public function targetGap(int $targetAmount, Collection $reports): int
    {
        return max(0, $targetAmount - (int) $reports->where('report_status', 'Disetujui')->sum('additional_investment'));
    }
}
