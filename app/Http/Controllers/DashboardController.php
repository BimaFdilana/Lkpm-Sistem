<?php

namespace App\Http\Controllers;

use App\GapCalculator;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\TargetPeriod;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(GapCalculator $calculator): View
    {
        $user = request()->user();
        $target = TargetPeriod::query()->where('is_active', true)->first()
            ?? TargetPeriod::query()->where('year', 2026)->where('quarter', 'TW III')->first();
        $reportQuarter = str_replace('TW ', 'Triwulan ', $target?->quarter ?? 'TW III');
        $reports = LkpmReport::query()->where('report_year', $target?->year ?? 2026)->where('report_quarter', $reportQuarter)->where('is_canonical', true)->get();
        $validRealization = (int) $reports->where('report_status', 'Disetujui')->sum('additional_investment');
        $approvedReports = $reports->where('report_status', 'Disetujui')->count();
        $annualTarget = (int) ($target?->annual_target ?: 7900000000000);
        $baselineRealization = (int) ($target?->baseline_realization ?? 2810000000000);
        $quarterTarget = (int) ($target?->target_amount ?? 2545000000000);
        $assignmentQuery = Assignment::query()
            ->where('status', 'active')
            ->where('is_task_active', true)
            ->when($target, fn ($query) => $query->where('year', $target->year)->where('quarter', $target->quarter));
        $assignments = (clone $assignmentQuery)->with('followUps')->get();
        $latestFollowUps = $assignments->map(fn (Assignment $assignment) => $assignment->followUps->sortByDesc('created_at')->first())->filter();
        $myAssignments = $user->role === 'pic'
            ? (clone $assignmentQuery)->where('pic_id', $user->id)->count()
            : null;
        $snapshotDate = $target ? DailyPrioritySnapshot::query()->where('target_period_id', $target->id)->max('snapshot_date') : null;
        $priorityRows = $snapshotDate && $target ? DailyPrioritySnapshot::query()->where('target_period_id', $target->id)->whereDate('snapshot_date', $snapshotDate)->get() : collect();
        $previousDate = $target && $snapshotDate ? DailyPrioritySnapshot::query()->where('target_period_id', $target->id)->whereDate('snapshot_date', '<', $snapshotDate)->max('snapshot_date') : null;
        $previousRows = $previousDate && $target ? DailyPrioritySnapshot::query()->where('target_period_id', $target->id)->whereDate('snapshot_date', $previousDate)->get() : collect();
        $greenProjection = (int) $priorityRows->where('candidate_tier', 'hijau')->sum('projected_contribution');
        $remainingTarget = (int) optional($priorityRows->first())->remaining_target;

        return view('dashboard', [
            'role' => $user->role,
            'target' => $target,
            'validRealization' => $validRealization,
            'targetGap' => $calculator->targetGap($quarterTarget, $reports),
            'annualTarget' => $annualTarget,
            'baselineRealization' => $baselineRealization,
            'annualRemaining' => max(0, $annualTarget - $baselineRealization),
            'quarterTarget' => $quarterTarget,
            'targetProgress' => $quarterTarget > 0 ? min(100, round(($validRealization / $quarterTarget) * 100, 1)) : 0,
            'reportCount' => $reports->count(),
            'approvedReports' => $approvedReports,
            'approvalRate' => $reports->isNotEmpty() ? round(($approvedReports / $reports->count()) * 100, 1) : 0,
            'unlinkedReports' => $reports->whereNull('project_id')->count(),
            'companies' => Company::count(),
            'assignments' => $assignments->count(),
            'pendingReports' => $reports->whereNotIn('report_status', ['Disetujui'])->count(),
            'contactedCompanies' => $latestFollowUps->whereIn('contact_status', ['sudah_dihubungi', 'tidak_dapat_dihubungi'])->count(),
            'confirmedCompanies' => $latestFollowUps->whereIn('confirmation_status', ['terkonfirmasi', 'terkonfirmasi_sebagian'])->count(),
            'indicatedAmount' => (int) $latestFollowUps->whereIn('confirmation_status', ['terkonfirmasi', 'terkonfirmasi_sebagian'])->sum('indicated_amount'),
            'primaryTargetCoverage' => (int) $assignments->where('is_primary_target', true)->sum('potential_at_assignment'),
            'myAssignments' => $myAssignments,
            'importBatches' => $user->role === 'programmer'
                ? ImportBatch::query()->latest()->limit(5)->get()
                : collect(),
            'prioritySummary' => ['date' => $snapshotDate, 'remainingTarget' => $remainingTarget, 'greenProjection' => $greenProjection, 'amberCount' => $priorityRows->where('candidate_tier', 'amber')->count(), 'reserveCount' => $priorityRows->where('candidate_tier', 'cadangan')->count(), 'previousRemainingTarget' => (int) optional($previousRows->first())->remaining_target, 'remainingChange' => $previousRows->isNotEmpty() ? $remainingTarget - (int) optional($previousRows->first())->remaining_target : null, 'checkpoint' => $target?->reporting_ends_at ? max(0, today()->diffInDays($target->reporting_ends_at, false)) : null, 'risk' => ! $snapshotDate ? 'belum tersedia' : ($greenProjection >= (int) ceil($remainingTarget * 1.3) ? 'aman' : ($greenProjection >= $remainingTarget ? 'perlu perhatian' : 'kritis'))],
        ]);
    }
}
