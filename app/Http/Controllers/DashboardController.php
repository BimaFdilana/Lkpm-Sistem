<?php

namespace App\Http\Controllers;

use App\GapCalculator;
use App\Models\AnnualTargetVersion;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(GapCalculator $calculator): View|RedirectResponse
    {
        $user = request()->user();

        if ($user->role === 'pic') {
            return redirect()->route('assignments.index');
        }

        $target = TargetPeriod::query()->where('is_active', true)->first()
            ?? TargetPeriod::query()->where('year', 2026)->where('quarter', 'TW III')->first();
        $reportQuarter = str_replace('TW ', 'Triwulan ', $target?->quarter ?? 'TW III');
        $dashboardYear = $target?->year ?? 2026;
        $reports = LkpmReport::query()->where('report_year', $dashboardYear)->where('report_quarter', $reportQuarter)->where('is_canonical', true)->get();
        $annualReports = LkpmReport::query()->where('report_year', $dashboardYear)->where('is_canonical', true)->get();
        $lkpmYears = LkpmReport::query()
            ->where('is_canonical', true)
            ->distinct()
            ->orderByDesc('report_year')
            ->pluck('report_year');
        $requestedLkpmYear = request()->query('lkpm_year');
        $lkpmFilterYear = $requestedLkpmYear === 'all'
            ? 'all'
            : (is_numeric($requestedLkpmYear) ? (int) $requestedLkpmYear : $dashboardYear);

        if ($lkpmFilterYear !== 'all' && ! $lkpmYears->contains($lkpmFilterYear)) {
            $lkpmFilterYear = $dashboardYear;
        }

        if ($lkpmYears->isEmpty()) {
            $lkpmYears = collect([$dashboardYear]);
        }

        $filteredYearReports = LkpmReport::query()
            ->with('project')
            ->where('is_canonical', true)
            ->when($lkpmFilterYear !== 'all', fn ($query) => $query->where('report_year', $lkpmFilterYear))
            ->get();
        $previousYearApprovedReports = LkpmReport::query()
            ->where('is_canonical', true)
            ->when($lkpmFilterYear !== 'all', fn ($query) => $query->where('report_year', $lkpmFilterYear - 1), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('report_status', 'Disetujui')
            ->get();
        $eligibleCompanyIds = Company::query()
            ->whereNotIn('business_scale', ['Usaha Mikro', 'Usaha Kecil'])
            ->whereHas('projects')
            ->pluck('id');
        $eligibleCompanyCount = $eligibleCompanyIds->count();
        $eligibleCompanyLookup = array_fill_keys($eligibleCompanyIds->all(), true);
        $eligibleProjectCount = Project::query()
            ->where('status', 'active')
            ->whereHas('company', fn ($query) => $query->whereNotIn('business_scale', ['Usaha Mikro', 'Usaha Kecil']))
            ->count();
        $lkpmReportYearCount = $lkpmFilterYear === 'all' ? max(1, $lkpmYears->count()) : 1;
        $expectedProjectReports = $eligibleProjectCount * $lkpmReportYearCount;
        $expectedCompanyReports = $eligibleCompanyCount * $lkpmReportYearCount;
        $lkpmQuarterSummaries = collect(['I', 'II', 'III', 'IV'])->map(function (string $quarter) use ($filteredYearReports, $previousYearApprovedReports, $eligibleCompanyLookup, $expectedProjectReports, $expectedCompanyReports, $lkpmFilterYear, $lkpmYears): array {
            $quarterLabel = "Triwulan {$quarter}";
            $quarterReports = $filteredYearReports->where('report_quarter', $quarterLabel);
            $approved = $quarterReports->where('report_status', 'Disetujui');
            $approvedCount = $approved->count();
            $previousCount = $previousYearApprovedReports->where('report_quarter', $quarterLabel)->count();
            $eligibleApproved = $approved->filter(fn (LkpmReport $report): bool => $report->project !== null && isset($eligibleCompanyLookup[$report->project->company_id]));
            $reportingCompanies = $eligibleApproved->map(fn (LkpmReport $report): string => $lkpmFilterYear === 'all'
                ? $report->report_year.'-'.$report->project->company_id
                : (string) $report->project->company_id)->unique()->count();
            $reportingProjects = $eligibleApproved->map(fn (LkpmReport $report): string => $lkpmFilterYear === 'all'
                ? $report->report_year.'-'.$report->project_id
                : (string) $report->project_id)->unique()->count();

            return [
                'quarter' => $quarter,
                'has_reports' => $quarterReports->isNotEmpty(),
                'approved_reports' => $approvedCount,
                'eligible_projects' => $expectedProjectReports,
                'reporting_projects' => $reportingProjects,
                'project_coverage' => $expectedProjectReports > 0 ? round($reportingProjects / $expectedProjectReports * 100, 1) : null,
                'reporting_companies' => $reportingCompanies,
                'eligible_companies' => $expectedCompanyReports,
                'company_coverage' => $expectedCompanyReports > 0 ? round($reportingCompanies / $expectedCompanyReports * 100, 1) : null,
                'previous_approved_reports' => $previousCount,
                'year_over_year' => $lkpmFilterYear !== 'all' && $previousCount > 0 ? round(($approvedCount - $previousCount) / $previousCount * 100, 1) : null,
                'year_label' => $lkpmFilterYear === 'all' ? 'seluruh tahun ('.$lkpmYears->min().'–'.$lkpmYears->max().')' : (string) $lkpmFilterYear,
                'all_years' => $lkpmFilterYear === 'all',
            ];
        })->values();
        $validRealization = (int) $reports->where('report_status', 'Disetujui')->sum('additional_investment');
        $approvedReports = $reports->where('report_status', 'Disetujui')->count();
        $latestAnnualTargetVersion = AnnualTargetVersion::query()
            ->where('year', $dashboardYear)
            ->latest('effective_at')
            ->latest('id')
            ->first();
        $periodTargetDistribution = TargetPeriod::query()
            ->where('year', $dashboardYear)
            ->pluck('target_amount', 'quarter')
            ->map(fn ($amount): int => (int) $amount)
            ->all();
        $annualTargetDistribution = $latestAnnualTargetVersion?->quarter_distribution ?? $periodTargetDistribution;
        $annualTargetDistributionComplete = collect(['TW I', 'TW II', 'TW III', 'TW IV'])
            ->every(fn (string $quarter): bool => array_key_exists($quarter, $annualTargetDistribution));
        $annualTarget = (int) ($latestAnnualTargetVersion?->annual_target ?? ($target?->annual_target ?: 7900000000000));
        $baselineRealization = (int) ($target?->baseline_realization ?? 2810000000000);
        $annualApprovedRealization = max($baselineRealization, (int) $annualReports->where('report_status', 'Disetujui')->sum('additional_investment'));
        $quarterTarget = (int) ($target?->target_amount ?? 2545000000000);
        $quarterOrder = ['TW I', 'TW II', 'TW III', 'TW IV'];
        $activeQuarterIndex = $target?->is_active ? array_search($target->quarter, $quarterOrder, true) : false;
        $previousQuarterShortfalls = collect();
        $previousQuartersWithoutTargets = collect();
        if ($activeQuarterIndex !== false && $activeQuarterIndex > 0) {
            $previousQuarters = array_slice($quarterOrder, 0, $activeQuarterIndex);
            $previousPeriods = TargetPeriod::query()
                ->where('year', $target->year)
                ->whereIn('quarter', $previousQuarters)
                ->get()
                ->keyBy('quarter');
            $previousQuartersWithoutTargets = collect($previousQuarters)
                ->reject(fn (string $quarter): bool => $previousPeriods->has($quarter))
                ->map(function (string $quarter) use ($annualReports): array {
                    $reportQuarter = str_replace('TW ', 'Triwulan ', $quarter);
                    $approved = $annualReports
                        ->where('report_quarter', $reportQuarter)
                        ->where('report_status', 'Disetujui');

                    return [
                        'quarter' => $quarter,
                        'reports' => $approved->count(),
                        'realized' => (int) $approved->sum('additional_investment'),
                    ];
                })
                ->values();
            $previousQuarterShortfalls = $previousPeriods->map(function (TargetPeriod $period) use ($annualReports): array {
                $reportQuarter = str_replace('TW ', 'Triwulan ', $period->quarter);
                $realized = (int) $annualReports
                    ->where('report_quarter', $reportQuarter)
                    ->where('report_status', 'Disetujui')
                    ->sum('additional_investment');

                return [
                    'quarter' => $period->quarter,
                    'target' => (int) $period->target_amount,
                    'realized' => $realized,
                    'remaining' => max(0, (int) $period->target_amount - $realized),
                ];
            })
                ->filter(fn (array $row): bool => $row['remaining'] > 0)
                ->sortBy(fn (array $row): int => array_search($row['quarter'], $quarterOrder, true))
                ->values();
        }
        $assignmentQuery = Assignment::query()
            ->where('status', 'active')
            ->where('is_task_active', true)
            ->when($target, fn ($query) => $query->where('year', $target->year)->where('quarter', $target->quarter));
        $assignments = (clone $assignmentQuery)->with('followUps')->get();
        $latestFollowUps = $assignments->map(fn (Assignment $assignment) => $assignment->followUps->sortByDesc('created_at')->first())->filter();
        $latestPrioritySnapshot = DailyPrioritySnapshot::query()
            ->with('importBatch')
            ->when($target, fn ($query) => $query->where('target_period_id', $target->id))
            ->latest('snapshot_date')
            ->latest('id')
            ->first();

        return view('dashboard', [
            'role' => $user->role,
            'target' => $target,
            'validRealization' => $validRealization,
            'targetGap' => $calculator->targetGap($quarterTarget, $reports),
            'annualTarget' => $annualTarget,
            'annualTargetDistribution' => $annualTargetDistribution,
            'annualTargetDistributionComplete' => $annualTargetDistributionComplete,
            'baselineRealization' => $baselineRealization,
            'annualApprovedRealization' => $annualApprovedRealization,
            'annualRemaining' => max(0, $annualTarget - $annualApprovedRealization),
            'annualProgress' => $annualTarget > 0 ? min(100, round(($annualApprovedRealization / $annualTarget) * 100, 1)) : 0,
            'lkpmYears' => $lkpmYears,
            'lkpmFilterYear' => $lkpmFilterYear,
            'lkpmQuarterSummaries' => $lkpmQuarterSummaries,
            'quarterTarget' => $quarterTarget,
            'targetProgress' => $quarterTarget > 0 ? min(100, round(($validRealization / $quarterTarget) * 100, 1)) : 0,
            'previousQuarterShortfalls' => $previousQuarterShortfalls,
            'previousQuartersWithoutTargets' => $previousQuartersWithoutTargets,
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
            'latestPrioritySnapshot' => $latestPrioritySnapshot,
            'myAssignments' => null,
            'importBatches' => $user->role === 'programmer'
                ? ImportBatch::query()->latest()->limit(5)->get()
                : collect(),
        ]);
    }
}
