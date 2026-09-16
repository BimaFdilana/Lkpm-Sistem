<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Http\Requests\StoreFollowUpRequest;
use App\Models\Assignment;
use App\Models\FollowUp;
use App\Models\TargetPeriod;
use App\ReserveActivationService;
use Illuminate\Http\RedirectResponse;

class FollowUpController extends Controller
{
    public function store(StoreFollowUpRequest $request, Assignment $assignment, AuditLogger $auditLogger, ReserveActivationService $reserveActivationService): RedirectResponse
    {
        $followUp = FollowUp::create([
            ...$request->validated(),
            'status' => $request->string('contact_status')->toString(),
            'assignment_id' => $assignment->id,
            'created_by' => $request->user()->id,
        ]);
        $auditLogger->log($request->user(), 'follow_up_created', $followUp, ['assignment_id' => $assignment->id]);
        $period = TargetPeriod::query()->where('is_active', true)->first();
        $activated = $period ? $reserveActivationService->promoteIfRequired($period) : 0;

        return back()->with('status', 'Tindak lanjut perusahaan telah disimpan.'.($activated > 0 ? " {$activated} perusahaan cadangan otomatis menjadi tugas aktif." : ''));
    }
}
