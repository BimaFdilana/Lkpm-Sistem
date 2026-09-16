<?php

namespace App\Http\Controllers;

use App\DailyPriorityEngine;
use App\Models\DailyPrioritySnapshot;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DailyPriorityController extends Controller
{
    public function index(): View
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        $latestDate = $period ? DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->max('snapshot_date') : null;
        $snapshots = $latestDate && $period
            ? DailyPrioritySnapshot::query()->with(['company', 'project'])->where('target_period_id', $period->id)->whereDate('snapshot_date', $latestDate)->orderByRaw("CASE candidate_tier WHEN 'hijau' THEN 1 WHEN 'cadangan' THEN 2 WHEN 'amber' THEN 3 ELSE 4 END")->orderBy('priority_rank')->paginate(30)
            : DailyPrioritySnapshot::query()->whereRaw('1 = 0')->paginate(30);

        $summary = $latestDate && $period ? [
            'remaining_target' => (int) optional($snapshots->first())->remaining_target,
            'valid_momentum' => (int) $snapshots->sum('valid_momentum'),
            'green_projection' => (int) $snapshots->where('candidate_tier', 'hijau')->sum('projected_contribution'),
        ] : null;
        return view('priority.index', compact('period', 'latestDate', 'snapshots', 'summary'));
    }

    public function run(DailyPriorityEngine $engine): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $alreadyExists = DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', today())->exists();
        $engine->snapshot($period);

        return back()->with('status', $alreadyExists
            ? 'Snapshot rekomendasi hari ini sudah terkunci; data historis tidak ditimpa.'
            : 'Snapshot simulasi prioritas hari ini berhasil dibuat. Assignment PIC tidak berubah.');
    }
}
