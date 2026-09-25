<?php

namespace App\Http\Controllers;

use App\DailyPriorityEngine;
use App\Models\DailyPrioritySnapshot;
use App\Models\DailyProjectSnapshot;
use App\Models\QuarterlyProjectBaseline;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DailyPriorityController extends Controller
{
    public function index(): View
    {
        $tab = request()->query('tab') === 'snapshots' ? 'snapshots' : 'priority';
        $period = TargetPeriod::query()->where('is_active', true)->first();
        $latestDate = $period ? DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->max('snapshot_date') : null;
        $latestSnapshots = $latestDate && $period
            ? DailyPrioritySnapshot::query()
                ->where('target_period_id', $period->id)
                ->whereDate('snapshot_date', $latestDate)
            : null;
        $snapshots = $latestSnapshots
            ? (clone $latestSnapshots)
                ->with(['company', 'project'])
                ->orderByRaw("CASE candidate_tier WHEN 'hijau' THEN 1 WHEN 'cadangan' THEN 2 WHEN 'amber' THEN 3 ELSE 4 END")
                ->orderByRaw("CASE WHEN candidate_tier = 'amber' THEN remaining_potential ELSE 0 END DESC")
                ->orderBy('priority_rank')
                ->paginate(30)
            : DailyPrioritySnapshot::query()->whereRaw('1 = 0')->paginate(30);

        $summary = $latestSnapshots ? [
            'remaining_target' => (int) (clone $latestSnapshots)->value('remaining_target'),
            'valid_momentum' => (int) (clone $latestSnapshots)->sum('valid_momentum'),
            'green_projection' => (int) (clone $latestSnapshots)->where('candidate_tier', 'hijau')->sum('projected_contribution'),
            'amber_companies' => (int) (clone $latestSnapshots)->where('candidate_tier', 'amber')->distinct('company_id')->count('company_id'),
        ] : null;

        $priorityHistory = collect();
        $baselinePeriods = collect();
        $recentSnapshots = collect();

        if ($tab === 'snapshots') {
            $priorityHistory = DailyPrioritySnapshot::query()
                ->where('target_period_id', $period?->id ?? 0)
                ->selectRaw('snapshot_date, COUNT(DISTINCT company_id) as company_count, SUM(CASE WHEN candidate_tier = ? THEN projected_contribution ELSE 0 END) as green_projection', ['hijau'])
                ->groupBy('snapshot_date')
                ->orderByDesc('snapshot_date')
                ->limit(30)
                ->get();
            $baselinePeriods = QuarterlyProjectBaseline::query()
                ->selectRaw('year, quarter, COUNT(*) as project_count, SUM(CASE WHEN baseline_is_estimated = 0 THEN 1 ELSE 0 END) as complete_count, SUM(momentum_amount) as momentum_amount')
                ->groupBy('year', 'quarter')->orderByDesc('year')->orderBy('quarter')->get();
            $recentSnapshots = DailyProjectSnapshot::query()
                ->selectRaw('snapshot_date, year, quarter, COUNT(*) as project_count, SUM(CASE WHEN is_valid_realization = 1 THEN 1 ELSE 0 END) as valid_count, SUM(CASE WHEN is_valid_realization = 1 THEN accumulated_investment ELSE 0 END) as valid_accumulated')
                ->groupBy('snapshot_date', 'year', 'quarter')->orderByDesc('snapshot_date')->limit(30)->get();
        }

        return view('priority.index', compact('tab', 'period', 'latestDate', 'snapshots', 'summary', 'priorityHistory', 'baselinePeriods', 'recentSnapshots'));
    }

    public function run(DailyPriorityEngine $engine): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $alreadyExists = DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', today())->exists();
        $engine->snapshot($period);

        return redirect()->route('priority.index')->with('status', $alreadyExists
            ? 'Snapshot rekomendasi hari ini sudah terkunci; data historis tidak ditimpa.'
            : 'Snapshot simulasi prioritas hari ini berhasil dibuat. Assignment PIC tidak berubah.');
    }
}
