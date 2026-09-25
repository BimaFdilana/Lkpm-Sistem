<?php

namespace App\Http\Controllers;

use App\AssignmentQueueService;
use App\AuditLogger;
use App\Http\Requests\StoreFollowUpRequest;
use App\Models\Assignment;
use App\Models\FollowUp;
use App\Models\TargetPeriod;
use App\ReserveActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class FollowUpController extends Controller
{
    public function store(StoreFollowUpRequest $request, Assignment $assignment, AuditLogger $auditLogger, ReserveActivationService $reserveActivationService, AssignmentQueueService $queue): RedirectResponse
    {
        [$activated, $refilled] = DB::transaction(function () use ($request, $assignment, $auditLogger, $reserveActivationService, $queue): array {
            $assignment = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $wasCompleted = $assignment->followUps()->where('contact_status', '!=', 'belum_dihubungi')->exists();
            $followUp = FollowUp::create([
                ...$request->validated(),
                'status' => $request->string('contact_status')->toString(),
                'assignment_id' => $assignment->id,
                'created_by' => $request->user()->id,
            ]);
            $auditLogger->log($request->user(), 'follow_up_created', $followUp, ['assignment_id' => $assignment->id]);
            $period = TargetPeriod::query()->where('is_active', true)->first();
            $isCompletedNow = ! $wasCompleted && $followUp->contact_status !== 'belum_dihubungi';
            $matchesPeriod = $period && $period->year === $assignment->year && $period->quarter === $assignment->quarter;
            $refilled = $matchesPeriod && $isCompletedNow ? $queue->refillAfterFollowUp($period, $assignment) : false;
            $activated = $period ? $reserveActivationService->promoteIfRequired($period) : 0;

            return [$activated, $refilled];
        });

        return back()->with('status', 'Tindak lanjut perusahaan telah disimpan.'.($refilled ? ' Satu perusahaan prioritas berikutnya masuk ke tugas Anda.' : '').($activated > 0 ? " {$activated} perusahaan cadangan otomatis menjadi tugas aktif." : ''));
    }
}
