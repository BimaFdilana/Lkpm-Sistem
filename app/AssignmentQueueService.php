<?php

namespace App;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\LkpmReport;
use App\Models\TargetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentQueueService
{
    public const INITIAL_LIMIT = 100;

    public function latestSnapshotDate(TargetPeriod $period): ?string
    {
        $date = DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->latest('snapshot_date')->value('snapshot_date');

        return $date === null ? null : Carbon::parse($date)->toDateString();
    }

    /** @return Collection<int, Company> */
    public function rankedCompanies(TargetPeriod $period, ?string $snapshotDate = null): Collection
    {
        $snapshotDate ??= $this->latestSnapshotDate($period);
        if ($snapshotDate === null) {
            return collect();
        }

        $start = Carbon::parse($snapshotDate)->startOfDay();
        $snapshots = DailyPrioritySnapshot::query()
            ->with('company')
            ->where('target_period_id', $period->id)
            ->whereBetween('snapshot_date', [$start, $start->copy()->endOfDay()])
            ->get();
        $periodIndex = $period->year * 4 + $this->quarterNumber($period->quarter);
        $activity = LkpmReport::query()
            ->join('projects', 'projects.id', '=', 'lkpm_reports.project_id')
            ->where('lkpm_reports.is_canonical', true)
            ->where('lkpm_reports.report_status', 'Disetujui')
            ->whereBetween('lkpm_reports.report_year', [$period->year - 1, $period->year])
            ->whereIn('projects.company_id', $snapshots->pluck('company_id')->unique())
            ->get(['projects.company_id', 'lkpm_reports.report_year', 'lkpm_reports.report_quarter'])
            ->groupBy('company_id');

        return $snapshots->groupBy('company_id')->map(function (Collection $rows) use ($activity, $periodIndex): Company {
            $company = $rows->first()->company;
            $reportedPeriods = ($activity->get($company->id) ?? collect())
                ->map(fn ($report): int => $report->report_year * 4 + $this->quarterNumber($report->report_quarter))
                ->filter(fn (int $index): bool => $index < $periodIndex && $index >= $periodIndex - 4)
                ->unique();
            $queueType = $rows->contains('candidate_tier', 'hijau') ? 'primary'
                : ($rows->contains('candidate_tier', 'cadangan') || $rows->contains(fn ($row): bool => $row->candidate_tier === 'monitoring' && $row->projected_contribution !== null) ? 'reserve' : 'verification');
            $company->setAttribute('queue_type', $queueType);
            $company->setAttribute('project_count', $rows->count());
            $company->setAttribute('projected_contribution', (int) $rows->sum('projected_contribution'));
            $company->setAttribute('remaining_potential', (int) $rows->sum('remaining_potential'));
            $company->setAttribute('projection_sources', $rows->pluck('projection_source')->filter()->unique()->values());
            $company->setAttribute('activity_quarters', $reportedPeriods->count());
            $company->setAttribute('last_two_quarters', $reportedPeriods->filter(fn (int $index): bool => $index >= $periodIndex - 2)->count());
            $company->setAttribute('needs_verification', $queueType === 'verification');
            $company->setAttribute('assignable', $rows->contains(fn ($row): bool => (int) $row->remaining_potential > 0 && ! in_array($row->verification_status, ['lkpm_disetujui', 'selesai'], true)));

            return $company;
        })->sort(function (Company $left, Company $right): int {
            $queueOrder = ['primary' => 3, 'reserve' => 2, 'verification' => 1];
            $queueComparison = ($queueOrder[$right->queue_type] ?? 0) <=> ($queueOrder[$left->queue_type] ?? 0);
            if ($queueComparison !== 0) {
                return $queueComparison;
            }

            foreach (['activity_quarters', 'last_two_quarters', 'projected_contribution', 'remaining_potential'] as $attribute) {
                $comparison = (int) $right->{$attribute} <=> (int) $left->{$attribute};
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return mb_strtolower($left->name) <=> mb_strtolower($right->name);
        })->values()->each(fn (Company $company, int $index) => $company->setAttribute('priority_rank', $index + 1));
    }

    /** @param Collection<int, User> $pics */
    public function distributeInitial(TargetPeriod $period, Collection $pics, int $assignedBy): int
    {
        return DB::transaction(function () use ($period, $pics, $assignedBy): int {
            TargetPeriod::query()->whereKey($period->id)->lockForUpdate()->first();
            if (Assignment::query()->where('year', $period->year)->where('quarter', $period->quarter)->exists()) {
                return 0;
            }

            $candidates = $this->rankedCompanies($period)->filter(fn (Company $company): bool => $company->assignable)->take(self::INITIAL_LIMIT)->values();
            foreach ($candidates as $index => $company) {
                $round = intdiv($index, $pics->count());
                $position = $index % $pics->count();
                $picIndex = $round % 2 === 0 ? $position : $pics->count() - 1 - $position;
                $this->createAssignment(
                    $period,
                    $company,
                    $pics->values()->get($picIndex)->id,
                    $assignedBy,
                    'Pembagian awal dari antrean prioritas; metode ular.',
                    $company->queue_type !== 'reserve',
                );
            }

            return $candidates->count();
        });
    }

    public function refillAfterFollowUp(TargetPeriod $period, Assignment $completed): bool
    {
        return DB::transaction(function () use ($period, $completed): bool {
            TargetPeriod::query()->whereKey($period->id)->lockForUpdate()->first();
            $alreadyAssigned = Assignment::query()->where('year', $period->year)->where('quarter', $period->quarter)->pluck('company_id')->all();
            $next = $this->rankedCompanies($period)->first(fn (Company $company): bool => $company->assignable && ! in_array($company->id, $alreadyAssigned, true));
            if ($next === null) {
                return false;
            }

            $this->createAssignment($period, $next, $completed->pic_id, $completed->assigned_by, 'Pengganti otomatis setelah PIC menindaklanjuti tugas sebelumnya.', true);

            return true;
        });
    }

    private function createAssignment(TargetPeriod $period, Company $company, int $picId, int $assignedBy, string $reason, bool $activate): void
    {
        Assignment::create([
            'company_id' => $company->id,
            'pic_id' => $picId,
            'assigned_by' => $assignedBy,
            'year' => $period->year,
            'quarter' => $period->quarter,
            'status' => 'active',
            'priority_rank' => $company->priority_rank,
            'is_primary_target' => $company->queue_type === 'primary',
            'queue_type' => $company->queue_type,
            'is_task_active' => $activate,
            'activated_at' => $activate ? now() : null,
            'potential_at_assignment' => $company->needs_verification ? 0 : $company->projected_contribution,
            'assigned_at' => now(),
            'reason' => $reason,
        ]);
    }

    private function quarterNumber(string $quarter): int
    {
        $quarter = mb_strtoupper($quarter);
        if (preg_match('/\b(?:TW|TRIWULAN)\s*([1-4])\b/', $quarter, $matches)) {
            return (int) $matches[1];
        }

        return match (true) {
            str_contains($quarter, 'IV') => 4,
            str_contains($quarter, 'III') => 3,
            str_contains($quarter, 'II') => 2,
            default => 1,
        };
    }
}
