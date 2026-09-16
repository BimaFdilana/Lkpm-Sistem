<?php

namespace App;

use App\Models\LkpmReport;
use App\Models\QuarterlyProjectBaseline;
use Illuminate\Support\Collection;

class QuarterlyBaselineBuilder
{
    /** @param array<int>|null $projectIds */
    public function build(int $fromYear = 2021, int $toYear = 2026, ?array $projectIds = null): int
    {
        $reports = LkpmReport::query()
            ->where('is_canonical', true)
            ->where('report_status', 'Disetujui')
            ->whereNotNull('project_id')
            ->whereBetween('report_year', [$fromYear, $toYear])
            ->when($projectIds, fn ($query) => $query->whereIn('project_id', $projectIds))
            ->orderBy('project_id')
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($reports->groupBy('project_id') as $projectReports) {
            $byPeriod = $this->latestReportPerPeriod($projectReports);
            $previous = null;
            for ($year = $fromYear; $year <= $toYear; $year++) {
                foreach (['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'] as $quarter) {
                    $report = $byPeriod->get($year.'|'.$quarter);
                    if ($report === null) continue;
                    $baselineKnown = $previous !== null;
                    $rows[] = [
                        'project_id' => $report->project_id,
                        'year' => $year,
                        'quarter' => $quarter,
                        'baseline_report_id' => $previous?->id,
                        'ending_report_id' => $report->id,
                        'baseline_amount' => (int) ($previous?->accumulated_investment ?? 0),
                        'ending_amount' => (int) $report->accumulated_investment,
                        'momentum_amount' => $baselineKnown ? (int) $report->accumulated_investment - (int) $previous->accumulated_investment : null,
                        'baseline_is_estimated' => ! $baselineKnown,
                        'calculated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $previous = $report;
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            QuarterlyProjectBaseline::query()->upsert($chunk, ['project_id', 'year', 'quarter'], ['baseline_report_id', 'ending_report_id', 'baseline_amount', 'ending_amount', 'momentum_amount', 'baseline_is_estimated', 'calculated_at', 'updated_at']);
        }

        return count($rows);
    }

    /** @param Collection<int, LkpmReport> $reports @return Collection<string, LkpmReport> */
    private function latestReportPerPeriod(Collection $reports): Collection
    {
        return $reports->groupBy(fn (LkpmReport $report) => $report->report_year.'|'.$report->report_quarter)
            ->map(fn (Collection $items) => $items->sortBy(fn (LkpmReport $report) => [$report->reported_at?->getTimestamp() ?? 0, $report->id])->last());
    }
}
