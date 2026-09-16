<?php

namespace App\Http\Controllers;

use App\Models\DailyProjectSnapshot;
use App\Models\QuarterlyProjectBaseline;
use Illuminate\View\View;

class SnapshotController extends Controller
{
    public function index(): View
    {
        return view('snapshots.index', [
            'baselinePeriods' => QuarterlyProjectBaseline::query()
                ->selectRaw('year, quarter, COUNT(*) as project_count, SUM(CASE WHEN baseline_is_estimated = 0 THEN 1 ELSE 0 END) as complete_count, SUM(momentum_amount) as momentum_amount')
                ->groupBy('year', 'quarter')->orderByDesc('year')->orderBy('quarter')->get(),
            'recentSnapshots' => DailyProjectSnapshot::query()
                ->selectRaw('snapshot_date, year, quarter, COUNT(*) as project_count, SUM(CASE WHEN is_valid_realization = 1 THEN 1 ELSE 0 END) as valid_count, SUM(CASE WHEN is_valid_realization = 1 THEN accumulated_investment ELSE 0 END) as valid_accumulated')
                ->groupBy('snapshot_date', 'year', 'quarter')->orderByDesc('snapshot_date')->limit(30)->get(),
        ]);
    }
}
