<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Models\Assignment;
use App\Models\CheckpointSnapshot;
use App\Models\TargetPeriod;
use Illuminate\Http\RedirectResponse;

class CheckpointSnapshotController extends Controller
{
    public function store(AuditLogger $auditLogger): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');

        $assignments = Assignment::query()->with('followUps')->where('status', 'active')->where('is_task_active', true)->where('year', $period->year)->where('quarter', $period->quarter)->get();
        $latestFollowUps = $assignments->map(fn (Assignment $assignment) => $assignment->followUps->sortByDesc('created_at')->first())->filter();
        $snapshot = CheckpointSnapshot::create([
            'target_period_id' => $period->id,
            'checkpoint_at' => today(),
            'kind' => 'manual',
            'assigned_company_count' => $assignments->count(),
            'contacted_company_count' => $latestFollowUps->whereIn('contact_status', ['sudah_dihubungi', 'tidak_dapat_dihubungi'])->count(),
            'confirmed_company_count' => $latestFollowUps->whereIn('confirmation_status', ['terkonfirmasi', 'terkonfirmasi_sebagian'])->count(),
            'indicated_amount' => (int) $latestFollowUps->whereIn('confirmation_status', ['terkonfirmasi', 'terkonfirmasi_sebagian'])->sum('indicated_amount'),
            'primary_target_coverage' => (int) $assignments->where('is_primary_target', true)->sum('potential_at_assignment'),
            'created_by' => request()->user()->id,
            'summary' => ['period' => $period->year.' '.$period->quarter],
        ]);
        $auditLogger->log(request()->user(), 'checkpoint_snapshot_created', $snapshot, ['target_period_id' => $period->id]);

        return back()->with('status', 'Snapshot checkpoint berhasil dibuat.');
    }
}
