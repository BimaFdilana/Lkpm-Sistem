<?php

namespace App;

use App\Models\Assignment;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReserveActivationService
{
    /** Activate one fair batch: one standby company for each PIC. */
    public function promoteIfRequired(TargetPeriod $period): int
    {
        $primaryAssignments = Assignment::query()
            ->with('followUps')
            ->where(['year' => $period->year, 'quarter' => $period->quarter, 'status' => 'active', 'queue_type' => 'primary'])
            ->get();
        $latestFollowUps = $primaryAssignments->map(fn (Assignment $assignment) => $assignment->followUps->sortByDesc('created_at')->first())->filter();
        $forecast = (int) $latestFollowUps
            ->whereIn('confirmation_status', ['terkonfirmasi', 'terkonfirmasi_sebagian'])
            ->sum('indicated_amount');
        $riskSignals = $latestFollowUps->filter(fn ($followUp) => $followUp->contact_status === 'tidak_dapat_dihubungi'
            || in_array($followUp->confirmation_status, ['terkonfirmasi_sebagian', 'nilai_kurang', 'tidak_sesuai'], true))
            ->count();

        if ($forecast >= (int) $period->buffer_amount || $riskSignals === 0) {
            return 0;
        }

        $pics = User::query()->where('role', 'pic')->orderBy('id')->get();
        if ($pics->isEmpty()) {
            return 0;
        }

        $alreadyActivatedBatches = intdiv(
            Assignment::query()->where(['year' => $period->year, 'quarter' => $period->quarter, 'status' => 'active', 'queue_type' => 'reserve', 'is_task_active' => true])->count(),
            $pics->count(),
        );
        if ($riskSignals <= $alreadyActivatedBatches) {
            return 0;
        }

        return DB::transaction(function () use ($pics, $period): int {
            $activated = 0;
            foreach ($pics as $pic) {
                $assignment = Assignment::query()
                    ->where(['year' => $period->year, 'quarter' => $period->quarter, 'status' => 'active', 'queue_type' => 'reserve', 'is_task_active' => false, 'pic_id' => $pic->id])
                    ->orderBy('priority_rank')
                    ->lockForUpdate()
                    ->first();
                if ($assignment !== null) {
                    $assignment->update(['is_task_active' => true, 'activated_at' => now()]);
                    $activated++;
                }
            }

            return $activated;
        });
    }
}
