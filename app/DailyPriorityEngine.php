<?php

namespace App;

use App\Models\Assignment;
use App\Models\DailyPrioritySnapshot;
use App\Models\LkpmReport;
use App\Models\PriorityProjectBaseline;
use App\Models\Project;
use App\Models\TargetPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Builds immutable, project-level recommendation snapshots. It never changes assignments. */
class DailyPriorityEngine
{
    /** Capture the period baseline once, immediately when a period becomes active. */
    public function captureBaselines(TargetPeriod $period): void
    {
        $quarterLabel = str_replace('TW ', 'Triwulan ', $period->quarter);
        Project::query()->whereHas('company', fn ($query) => $query->whereNotIn('business_scale', ['Usaha Mikro', 'Usaha Kecil']))
            ->with(['reports' => fn ($query) => $query->where('is_canonical', true)])
            ->get()->each(fn (Project $project) => $this->baselineFor($project, $period, $quarterLabel));
    }

    public function snapshot(TargetPeriod $period, ?Carbon $date = null, ?int $importBatchId = null): Collection
    {
        $date ??= today();
        $existing = DailyPrioritySnapshot::query()
            ->where('target_period_id', $period->id)->whereDate('snapshot_date', $date)->exists();
        if ($existing) {
            return DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', $date)->get();
        }

        $projects = Project::query()
            ->whereHas('company', fn ($query) => $query->whereNotIn('business_scale', ['Usaha Mikro', 'Usaha Kecil']))
            ->with(['company', 'reports' => fn ($query) => $query->where('is_canonical', true)])
            ->get();
        $latestFollowUps = $this->latestFollowUps($period);
        $quarterLabel = str_replace('TW ', 'Triwulan ', $period->quarter);
        $rows = $projects->map(fn (Project $project) => $this->calculate($project, $period, $quarterLabel, $latestFollowUps->get($project->company_id), $this->baselineFor($project, $period, $quarterLabel)));
        $remainingTarget = max(0, (int) $period->target_amount - (int) $rows->sum('valid_momentum'));
        $urgency = $this->urgencyFactor($period, $date);
        $rows = $rows->map(function (array $row) use ($remainingTarget, $urgency): array {
            $row['remaining_target'] = $remainingTarget;
            $row['priority_score'] = $row['projected_contribution'] === null ? null : round(($row['projected_contribution'] / max(1, $remainingTarget)) * 100 * $urgency * ($row['valid_momentum'] === 0 ? 1.2 : 1.0), 4);
            $row['calculation_meta']['urgency_factor'] = $urgency;

            return $row;
        });

        // Project calculations are intentionally retained, then summed before ranking so a
        // completed project cannot hide another unreported project at the same company.
        $ranked = $rows->filter(fn (array $row) => $row['projected_contribution'] !== null && $row['remaining_potential'] > 0)
            ->groupBy('company_id')
            ->map(fn (Collection $companyRows) => [
                'company_id' => $companyRows->first()['company_id'],
                'project_ids' => $companyRows->pluck('project_id')->all(),
                'projected_contribution' => (int) $companyRows->sum('projected_contribution'),
                'priority_score' => (float) $companyRows->sum('priority_score'),
            ])
            ->sortByDesc('priority_score')->values();
        $coverage = 0;
        $primaryLimit = (int) ceil($remainingTarget * 1.3);
        $reserveLimit = (int) ceil($remainingTarget * 1.5);
        $ranked = $ranked->map(function (array $row, int $index) use (&$coverage, $primaryLimit, $reserveLimit): array {
            $amount = (int) $row['projected_contribution'];
            $row['priority_rank'] = $index + 1;
            $row['candidate_tier'] = $coverage < $primaryLimit ? 'hijau' : ($coverage < $reserveLimit ? 'cadangan' : 'monitoring');
            $coverage += $amount;

            return $row;
        });
        $rankByProject = $ranked->flatMap(fn (array $row) => collect($row['project_ids'])->mapWithKeys(fn (int $projectId) => [$projectId => $row]));

        return DB::transaction(function () use ($rows, $rankByProject, $period, $date, $importBatchId): Collection {
            foreach ($rows as $row) {
                $rank = $rankByProject->get($row['project_id']);
                DailyPrioritySnapshot::create([
                    ...$row,
                    'target_period_id' => $period->id,
                    'snapshot_date' => $date,
                    'import_batch_id' => $importBatchId,
                    'priority_rank' => $rank['priority_rank'] ?? null,
                    'candidate_tier' => $rank['candidate_tier'] ?? ($row['projected_contribution'] === null ? 'amber' : 'monitoring'),
                ]);
            }

            return DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', $date)->get();
        });
    }

    /** @return Collection<int, mixed> */
    private function latestFollowUps(TargetPeriod $period): Collection
    {
        return Assignment::query()->with('followUps')
            ->where(['year' => $period->year, 'quarter' => $period->quarter, 'status' => 'active'])
            ->get()->mapWithKeys(fn (Assignment $assignment) => [$assignment->company_id => $assignment->followUps->sortByDesc('created_at')->first()])->filter();
    }

    /** @return array<string, mixed> */
    private function calculate(Project $project, TargetPeriod $period, string $quarterLabel, mixed $followUp, PriorityProjectBaseline $baselineRecord): array
    {
        $reports = $project->reports->sortBy(fn (LkpmReport $report) => sprintf('%04d-%02d-%010d', $report->report_year, $this->quarterNumber($report->report_quarter), $report->id))->values();
        $approved = $reports->where('report_status', 'Disetujui')->values();
        $baseline = (int) $baselineRecord->initial_accumulated_investment;
        $periodQuarter = $this->quarterNumber($quarterLabel);
        $currentValid = $approved->filter(fn (LkpmReport $report) => $report->report_year === $period->year && $this->quarterNumber($report->report_quarter) === $periodQuarter)->sortByDesc('id')->first();
        $currentMonitoring = $reports->filter(fn (LkpmReport $report) => $report->report_year === $period->year && $this->quarterNumber($report->report_quarter) === $periodQuarter)->sortByDesc('id')->first();
        $validAccumulated = (int) ($currentValid?->accumulated_investment ?? $baseline);
        $monitoringAccumulated = (int) ($currentMonitoring?->accumulated_investment ?? $validAccumulated);
        $planned = (int) $project->planned_investment;
        $remaining = max(0, $planned - $validAccumulated);
        $historical = $baselineRecord->historical_quarterly_realization;
        $picConfirmed = $followUp && in_array($followUp->confirmation_status, ['terkonfirmasi', 'terkonfirmasi_sebagian'], true) && $followUp->indicated_amount !== null;
        // A company-level PIC indication is only allocated automatically when it has one project; otherwise it stays amber for safe manual allocation.
        $singleProject = $project->company->projects()->count() === 1;
        $picAmount = $picConfirmed && $singleProject ? (int) $followUp->indicated_amount : null;
        $projection = $picAmount ?? $historical;
        $projection = $projection === null ? null : min($projection, $remaining);
        $isFinished = $followUp?->verification_status === 'selesai';
        $verification = $currentValid ? 'lkpm_disetujui' : ($isFinished ? 'selesai' : ($picConfirmed ? ($singleProject ? 'terkonfirmasi_pic' : 'perlu_alokasi_pic') : 'perlu_verifikasi'));

        return [
            'project_id' => $project->id, 'company_id' => $project->company_id, 'planned_investment' => $planned,
            'valid_accumulated_investment' => $validAccumulated, 'baseline_accumulated_investment' => $baseline,
            'valid_momentum' => max(0, $validAccumulated - $baseline), 'monitoring_accumulated_investment' => $monitoringAccumulated,
            'monitoring_momentum' => max(0, $monitoringAccumulated - $baseline), 'remaining_potential' => $remaining,
            'pic_indicated_amount' => $picAmount, 'historical_quarterly_realization' => $historical,
            'projected_contribution' => ($currentValid || $isFinished) ? 0 : $projection,
            'projection_source' => $currentValid ? 'approved_lkpm' : ($isFinished ? 'verification_complete' : ($picAmount !== null ? 'pic_confirmation' : ($historical !== null ? 'valid_history_median' : 'unknown'))),
            'lkpm_status' => $currentMonitoring?->report_status, 'verification_status' => $verification,
            'calculation_meta' => ['baseline_captured_at' => $baselineRecord->captured_at->toIso8601String(), 'company_pic_indication_requires_allocation' => $picConfirmed && ! $singleProject],
        ];
    }

    private function baselineFor(Project $project, TargetPeriod $period, string $quarterLabel): PriorityProjectBaseline
    {
        $existing = PriorityProjectBaseline::query()->where(['target_period_id' => $period->id, 'project_id' => $project->id])->first();
        if ($existing) {
            return $existing;
        }
        $approvedQuarterEnds = $project->reports
            ->where('report_status', 'Disetujui')
            ->groupBy(fn (LkpmReport $report): string => $report->report_year.'-'.$this->quarterNumber($report->report_quarter))
            ->map(fn (Collection $quarterReports): LkpmReport => $quarterReports
                ->sortBy(fn (LkpmReport $report): string => sprintf('%020d-%010d', $report->reported_at?->getTimestamp() ?? 0, $report->id))
                ->last())
            ->sortBy(fn (LkpmReport $report): string => sprintf('%04d-%02d', $report->report_year, $this->quarterNumber($report->report_quarter)))
            ->values();
        $prior = $approvedQuarterEnds
            ->filter(fn (LkpmReport $report): bool => $report->report_year < $period->year || ($report->report_year === $period->year && $this->quarterNumber($report->report_quarter) < $this->quarterNumber($quarterLabel)))
            ->values();
        $deltas = $prior
            ->map(fn (LkpmReport $report, int $index): ?int => $index === 0 ? null : max(0, (int) $report->accumulated_investment - (int) $prior[$index - 1]->accumulated_investment))
            ->filter(fn (?int $value): bool => $value !== null)
            ->take(-4);

        return PriorityProjectBaseline::create(['target_period_id' => $period->id, 'project_id' => $project->id, 'initial_accumulated_investment' => (int) optional($prior->last())->accumulated_investment, 'historical_quarterly_realization' => $deltas->count() >= 2 ? (int) round($deltas->median()) : null, 'captured_at' => now()]);
    }

    private function urgencyFactor(TargetPeriod $period, Carbon $date): float
    {
        $days = $period->reporting_ends_at ? $date->diffInDays($period->reporting_ends_at, false) : 99;

        return $days <= 1 ? 2.0 : ($days <= 7 ? 1.6 : ($days <= 14 ? 1.3 : 1.0));
    }

    private function quarterNumber(string $quarter): int
    {
        $digits = preg_replace('/\D+/', '', $quarter);
        if ($digits !== '') {
            return (int) $digits;
        }

        return match (true) {
            str_contains(mb_strtoupper($quarter), 'IV') => 4,
            str_contains(mb_strtoupper($quarter), 'III') => 3,
            str_contains(mb_strtoupper($quarter), 'II') => 2,
            default => 1,
        };
    }
}
