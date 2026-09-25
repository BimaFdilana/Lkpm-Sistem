<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Http\Requests\StoreAnnualTargetVersionRequest;
use App\Http\Requests\UpdateDashboardAnnualTargetRequest;
use App\Models\AnnualTargetVersion;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnnualTargetController extends Controller
{
    public function index(): View
    {
        $year = (int) request('year', now()->year);

        return view('annual-targets.index', ['year' => $year, 'versions' => AnnualTargetVersion::query()->with('createdBy')->where('year', $year)->latest('effective_at')->get(), 'periods' => TargetPeriod::query()->where('year', $year)->orderByRaw("CASE quarter WHEN 'TW I' THEN 1 WHEN 'TW II' THEN 2 WHEN 'TW III' THEN 3 ELSE 4 END")->get()]);
    }

    public function store(StoreAnnualTargetVersionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $distribution = ['TW I' => (int) $data['tw_1'], 'TW II' => (int) $data['tw_2'], 'TW III' => (int) $data['tw_3'], 'TW IV' => (int) $data['tw_4']];
        $version = DB::transaction(function () use ($data, $distribution, $request): AnnualTargetVersion {
            $version = AnnualTargetVersion::create(['year' => $data['year'], 'annual_target' => $data['annual_target'], 'quarter_distribution' => $distribution, 'reason' => $data['reason'], 'effective_at' => now(), 'created_by' => $request->user()->id]);
            // Closed and active periods preserve the target used for their historical decisions.
            TargetPeriod::query()->where('year', $data['year'])->where('is_active', false)->where('is_closed', false)->whereNull('target_frozen_at')->each(function (TargetPeriod $period) use ($version, $distribution): void {
                $period->update(['annual_target_version_id' => $version->id, 'annual_target' => $version->annual_target, 'target_amount' => $distribution[$period->quarter]]);
            });

            return $version;
        });
        $auditLogger->log($request->user(), 'annual_target_version_created', $version, ['distribution' => $distribution]);

        return redirect()->route('annual-targets.index', ['year' => $data['year']])->with('status', 'Versi target tahunan disimpan. Periode aktif dan tertutup tidak diubah.');
    }

    public function storeFromDashboard(UpdateDashboardAnnualTargetRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $year = $request->integer('year');
        $annualTarget = $request->integer('annual_target');
        $distribution = [
            'TW I' => $request->integer('tw_1'),
            'TW II' => $request->integer('tw_2'),
            'TW III' => $request->integer('tw_3'),
            'TW IV' => $request->integer('tw_4'),
        ];

        $version = DB::transaction(function () use ($year, $annualTarget, $distribution, $request): AnnualTargetVersion {
            $version = AnnualTargetVersion::create([
                'year' => $year,
                'annual_target' => $annualTarget,
                'quarter_distribution' => $distribution,
                'reason' => 'Penyesuaian target tahunan melalui Dashboard Kadis.',
                'effective_at' => now(),
                'created_by' => $request->user()->id,
            ]);
            TargetPeriod::query()->where('year', $year)->where('is_active', false)->where('is_closed', false)->whereNull('target_frozen_at')->each(function (TargetPeriod $period) use ($version, $distribution): void {
                $period->update(['annual_target_version_id' => $version->id, 'annual_target' => $version->annual_target, 'target_amount' => $distribution[$period->quarter]]);
            });

            return $version;
        });
        $auditLogger->log($request->user(), 'annual_target_version_created_from_dashboard', $version, ['distribution' => $distribution]);

        return redirect()->route('dashboard')->with('status', 'Target tahunan dan pembagian TW I–IV disimpan sebagai versi baru. Periode aktif dan tertutup tetap dipertahankan.');
    }
}
