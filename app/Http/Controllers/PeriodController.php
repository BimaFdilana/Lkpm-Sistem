<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\DailyPriorityEngine;
use App\Http\Requests\StoreTargetPeriodRequest;
use App\Models\AnnualTargetVersion;
use App\Models\CheckpointSnapshot;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(): View
    {
        return view('periods.index', [
            'periods' => TargetPeriod::query()->with('approvedBy')->orderByDesc('year')->orderBy('quarter')->get(),
            'snapshots' => CheckpointSnapshot::query()->with(['period', 'createdBy'])->latest('checkpoint_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(StoreTargetPeriodRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validated();
        $annualVersion = AnnualTargetVersion::query()->where('year', $validated['year'])->latest('effective_at')->first();
        $targetAmount = $annualVersion
            ? (int) $annualVersion->quarter_distribution[$validated['quarter']]
            : max(0, intdiv(max(0, $validated['annual_target'] - $validated['baseline_realization']), 5 - array_search($validated['quarter'], ['TW I', 'TW II', 'TW III', 'TW IV'], true) - 1));
        $period = TargetPeriod::query()->updateOrCreate(
            ['year' => $validated['year'], 'quarter' => $validated['quarter']],
            [...$validated, 'annual_target_version_id' => $annualVersion?->id, 'annual_target' => $annualVersion?->annual_target ?? $validated['annual_target'], 'target_amount' => $targetAmount, 'buffer_amount' => $validated['buffer_amount'] ?: $targetAmount, 'approved_by' => $request->user()->id, 'approved_at' => now()],
        );
        $auditLogger->log($request->user(), 'target_period_saved', $period, ['target_amount' => $targetAmount]);

        return back()->with('status', 'Periode dan target carry-over berhasil disimpan.');
    }

    public function activate(TargetPeriod $period, AuditLogger $auditLogger, DailyPriorityEngine $dailyPriorityEngine): RedirectResponse
    {
        DB::transaction(function () use ($period, $auditLogger): void {
            TargetPeriod::query()->where('is_active', true)->update(['is_active' => false]);
            $period->update(['is_active' => true, 'target_frozen_at' => $period->target_frozen_at ?? now()]);
            $auditLogger->log(request()->user(), 'target_period_activated', $period);
        });
        $dailyPriorityEngine->captureBaselines($period->fresh());

        return back()->with('status', "Periode {$period->year} {$period->quarter} telah diaktifkan.");
    }
}
