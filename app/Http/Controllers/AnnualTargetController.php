<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Http\Requests\StoreAnnualTargetVersionRequest;
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
}
