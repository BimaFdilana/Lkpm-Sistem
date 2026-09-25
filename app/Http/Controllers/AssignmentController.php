<?php

namespace App\Http\Controllers;

use App\AssignmentQueueService;
use App\AuditLogger;
use App\GapCalculator;
use App\Http\Requests\UpdateAssignmentPicRequest;
use App\Models\Assignment;
use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(AssignmentQueueService $queue): View
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        $isPic = auth()->user()->role === 'pic';
        $tab = $isPic && request()->query('tab') === 'history' ? 'history' : 'tasks';
        $query = Assignment::query()
            ->with([
                'company.projects.reports',
                'pic',
                'followUps' => fn ($query) => $query
                    ->when($isPic, fn ($followUps) => $followUps->where('created_by', auth()->id()))
                    ->latest()
                    ->limit(1),
            ])
            ->where('status', 'active')
            ->when($isPic, fn ($query) => $query->where('is_task_active', true))
            ->when($period, fn ($query) => $query->where('year', $period->year)->where('quarter', $period->quarter))
            ->orderByDesc('is_task_active')
            ->orderBy('priority_rank');
        $taskCount = null;
        $historyCount = null;

        if ($isPic) {
            $query->where('pic_id', auth()->id());
            $ownFollowUps = fn ($followUps) => $followUps->where('created_by', auth()->id())->where('contact_status', '!=', 'belum_dihubungi');
            $taskCount = (clone $query)->whereDoesntHave('followUps', $ownFollowUps)->count();
            $historyCount = (clone $query)->whereHas('followUps', $ownFollowUps)->count();
            $query = $tab === 'history'
                ? $query->whereHas('followUps', $ownFollowUps)
                : $query->whereDoesntHave('followUps', $ownFollowUps);
        }

        $candidateCompanies = collect();
        $snapshotDate = null;
        $assignedIds = [];
        if (! $isPic && $period !== null) {
            $snapshotDate = $queue->latestSnapshotDate($period);
            $candidateCompanies = $queue->rankedCompanies($period, $snapshotDate);
            $assignedIds = Assignment::query()->where('year', $period->year)->where('quarter', $period->quarter)->pluck('company_id')->all();
        }
        $candidatePage = max(1, (int) request()->query('candidate_page', 1));
        $candidatePaginator = (new LengthAwarePaginator(
            $candidateCompanies->slice(($candidatePage - 1) * 50, 50)->values(),
            $candidateCompanies->count(), 50, $candidatePage,
            ['path' => route('assignments.index'), 'query' => request()->query(), 'pageName' => 'candidate_page']
        ))->fragment('candidates');

        return view('assignments.index', [
            'assignments' => $query->paginate(20)->withQueryString(),
            'pics' => $isPic ? collect() : User::query()->where('role', 'pic')->orderBy('name')->get(),
            'period' => $period,
            'isPic' => $isPic,
            'tab' => $tab,
            'taskCount' => $taskCount,
            'historyCount' => $historyCount,
            'candidateCompanies' => $candidatePaginator,
            'candidateSnapshotDate' => $snapshotDate,
            'candidateEligibleCount' => $candidateCompanies->where('assignable', true)->count(),
            'candidateAssignedIds' => $assignedIds,
            'candidateHasAssignments' => $assignedIds !== [],
        ]);
    }

    public function rebalance(AssignmentQueueService $queue): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $pics = User::query()->where('role', 'pic')->orderBy('name')->get();
        abort_if($pics->isEmpty(), 422, 'Tambahkan minimal satu PIC aktif terlebih dahulu.');
        abort_if($queue->latestSnapshotDate($period) === null, 422, 'Buat snapshot prioritas harian terlebih dahulu.');
        $count = $queue->distributeInitial($period, $pics, auth()->id());
        abort_if($count === 0, 422, 'Tugas periode ini sudah dibagikan atau belum ada perusahaan dengan sisa potensi. Riwayat tugas tidak dihapus.');

        return back()->with('status', "{$count} perusahaan awal dibagi rata ke PIC dari antrean prioritas.");
    }

    public function candidates(AssignmentQueueService $queue): View
    {
        return $this->index($queue);
    }

    public function assignCandidates(AssignmentQueueService $queue): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $pics = User::query()->where('role', 'pic')->orderBy('name')->get();
        abort_if($pics->isEmpty(), 422, 'Tambahkan minimal satu PIC aktif terlebih dahulu.');

        $snapshotDate = $queue->latestSnapshotDate($period);
        abort_if($snapshotDate === null, 422, 'Buat snapshot prioritas harian terlebih dahulu sebelum membuat assignment PIC.');
        $count = $queue->distributeInitial($period, $pics, auth()->id());
        abort_if($count === 0, 422, 'Tugas periode ini sudah dibagikan atau belum ada perusahaan dengan sisa potensi. Riwayat tugas tidak dihapus.');

        return redirect()->route('assignments.index')->with('status', "{$count} perusahaan awal dibagi rata ke PIC. Berikutnya masuk otomatis setelah tindak lanjut.");
    }

    public function assignInitialVerification(AssignmentQueueService $queue): RedirectResponse
    {
        return $this->assignCandidates($queue);
    }

    public function show(Assignment $assignment, GapCalculator $calculator): View
    {
        abort_unless(auth()->user()->role === 'kepala_bagian' || $assignment->pic_id === auth()->id(), 403);
        $assignment->load(['company.contactSourceReport', 'company.projects.reports', 'pic', 'followUps.createdBy']);
        $projectRows = $assignment->company->projects
            ->sortBy('project_code')
            ->map(function ($project) use ($calculator): array {
                $report = $project->reports
                    ->where('is_canonical', true)
                    ->sortByDesc('reported_at')
                    ->first();

                return [
                    'project' => $project,
                    'report' => $report,
                    'potential' => $calculator->projectPotential($project, $report),
                ];
            });

        return view('assignments.show', ['assignment' => $assignment, 'projectRows' => $projectRows, 'pics' => User::query()->where('role', 'pic')->orderBy('name')->get()]);
    }

    public function updatePic(UpdateAssignmentPicRequest $request, Assignment $assignment, AuditLogger $auditLogger): RedirectResponse
    {
        $previousPicId = $assignment->pic_id;
        $assignment->update([
            'pic_id' => $request->integer('pic_id'),
            'assigned_by' => $request->user()->id,
            'reason' => 'Pemindahan PIC oleh Kepala Bagian',
        ]);
        $auditLogger->log($request->user(), 'assignment_pic_changed', $assignment, ['from_pic_id' => $previousPicId, 'to_pic_id' => $assignment->pic_id]);

        return back()->with('status', 'PIC penanggung jawab berhasil diperbarui.');
    }
}
