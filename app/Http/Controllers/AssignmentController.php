<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\GapCalculator;
use App\Http\Requests\UpdateAssignmentPicRequest;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\LkpmReport;
use App\Models\TargetPeriod;
use App\Models\User;
use App\PriorityAssignmentPlanner;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        $query = Assignment::query()
            ->with(['company.projects.reports', 'pic', 'followUps' => fn ($query) => $query->latest()->limit(1)])
            ->where('status', 'active')
            ->when($period, fn ($query) => $query->where('year', $period->year)->where('quarter', $period->quarter))
            ->orderByDesc('is_task_active')
            ->orderBy('priority_rank');
        if (auth()->user()->role === 'pic') {
            $query->where('pic_id', auth()->id());
        }

        return view('assignments.index', ['assignments' => $query->paginate(20), 'pics' => User::query()->where('role', 'pic')->orderBy('name')->get(), 'period' => $period]);
    }

    public function rebalance(PriorityAssignmentPlanner $planner): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $companies = Company::query()
            ->whereNotIn('business_scale', ['Usaha Mikro', 'Usaha Kecil'])
            ->with(['projects.reports'])
            ->get();
        $pics = User::query()->where('role', 'pic')->orderBy('name')->get();

        abort_if($pics->isEmpty(), 422, 'Tambahkan minimal satu PIC aktif terlebih dahulu.');
        abort_if($companies->isEmpty(), 422, 'Belum ada perusahaan Non-UMK yang dapat dibagikan.');
        DB::transaction(function () use ($companies, $pics, $period, $planner): void {
            Assignment::query()->where('year', $period->year)->where('quarter', $period->quarter)->where('status', 'active')->update(['status' => 'replaced']);

            foreach ($planner->plan($companies, $pics, $period) as $plan) {
                Assignment::create([
                    'company_id' => $plan['company']->id,
                    'pic_id' => $plan['pic_id'],
                    'assigned_by' => auth()->id(),
                    'year' => $period->year,
                    'quarter' => $period->quarter,
                    'status' => 'active',
                    'priority_rank' => $plan['priority_rank'],
                    'is_primary_target' => $plan['is_primary_target'],
                    'potential_at_assignment' => $plan['potential'],
                    'assigned_at' => now(),
                    'reason' => 'Pembagian metode ular: jumlah perusahaan seimbang, urutan berdasarkan prioritas LKPM dan potensi.',
                ]);
            }
        });

        return back()->with('status', 'Assignment PIC diperbarui dengan jumlah perusahaan seimbang dan prioritas potensi tersebar.');
    }

    public function candidates(): View
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');

        $candidates = $this->candidatePool($period);
        $primaryCandidates = $candidates->take(100)->values();
        $reserveCandidates = $candidates->slice(100, 100)->values();

        return view('assignments.candidates', [
            'period' => $period,
            'primaryCandidates' => $primaryCandidates,
            'reserveCandidates' => $reserveCandidates,
            'primaryPotential' => (int) $primaryCandidates->sum('potential'),
        ]);
    }

    public function assignCandidates(): RedirectResponse
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        abort_unless($period, 422, 'Aktifkan periode kerja terlebih dahulu.');
        $pics = User::query()->where('role', 'pic')->orderBy('name')->get();
        abort_if($pics->isEmpty(), 422, 'Tambahkan minimal satu PIC aktif terlebih dahulu.');

        $candidates = $this->candidatePool($period);
        $primaryCandidates = $candidates->take(100)->values();
        $reserveCandidates = $candidates->slice(100, 100)->values();
        abort_if($primaryCandidates->isEmpty(), 422, 'Belum ada kandidat utama yang memenuhi kriteria.');

        $pool = $primaryCandidates->map(fn (Company $company) => ['company' => $company, 'queue_type' => 'primary'])
            ->concat($reserveCandidates->map(fn (Company $company) => ['company' => $company, 'queue_type' => 'reserve']))
            ->values();

        DB::transaction(function () use ($period, $pics, $pool): void {
            Assignment::query()->where('year', $period->year)->where('quarter', $period->quarter)->where('status', 'active')->update(['status' => 'replaced']);
            foreach ($pool as $index => $item) {
                $round = intdiv($index, $pics->count());
                $position = $index % $pics->count();
                $picIndex = $round % 2 === 0 ? $position : $pics->count() - 1 - $position;
                $isPrimary = $item['queue_type'] === 'primary';
                Assignment::create([
                    'company_id' => $item['company']->id,
                    'pic_id' => $pics->values()->get($picIndex)->id,
                    'assigned_by' => auth()->id(),
                    'year' => $period->year,
                    'quarter' => $period->quarter,
                    'status' => 'active',
                    'priority_rank' => $index + 1,
                    'is_primary_target' => $isPrimary,
                    'queue_type' => $item['queue_type'],
                    'is_task_active' => $isPrimary,
                    'activated_at' => $isPrimary ? now() : null,
                    'potential_at_assignment' => (int) $item['company']->potential,
                    'assigned_at' => now(),
                    'reason' => $isPrimary ? 'Kandidat utama: dibagi rata metode ular.' : 'Kandidat cadangan: standby dan akan aktif otomatis bila proyeksi tim kurang dari buffer.',
                ]);
            }
        });

        return redirect()->route('assignments.index')->with('status', "Assignment dibuat dari {$primaryCandidates->count()} kandidat utama dan {$reserveCandidates->count()} cadangan standby.");
    }

    /** @return Collection<int, Company> */
    private function candidatePool(TargetPeriod $period): Collection
    {
        $reportQuarter = str_replace('TW ', 'Triwulan ', $period->quarter);
        $latestReportIds = LkpmReport::query()
            ->selectRaw('project_id, MAX(id) as latest_id')
            ->where('is_canonical', true)
            ->whereNotNull('project_id')
            ->groupBy('project_id');

        return Company::query()
            ->select(['companies.id', 'companies.name', 'companies.nib', 'companies.district'])
            ->selectRaw('COUNT(DISTINCT projects.id) as project_count')
            ->selectRaw('SUM(CASE WHEN projects.planned_investment > COALESCE(latest_report.accumulated_investment, 0) THEN projects.planned_investment - COALESCE(latest_report.accumulated_investment, 0) ELSE 0 END) as potential')
            ->join('projects', 'projects.company_id', '=', 'companies.id')
            ->leftJoinSub($latestReportIds, 'latest_report_id', fn ($join) => $join->on('latest_report_id.project_id', '=', 'projects.id'))
            ->leftJoin('lkpm_reports as latest_report', 'latest_report.id', '=', 'latest_report_id.latest_id')
            ->whereNotIn('companies.business_scale', ['Usaha Mikro', 'Usaha Kecil'])
            ->whereDoesntHave('projects.reports', fn ($query) => $query
                ->where('is_canonical', true)
                ->where('report_year', $period->year)
                ->where('report_quarter', $reportQuarter)
                ->where('report_status', 'Disetujui'))
            ->groupBy('companies.id', 'companies.name', 'companies.nib', 'companies.district')
            ->havingRaw('SUM(CASE WHEN projects.planned_investment > COALESCE(latest_report.accumulated_investment, 0) THEN projects.planned_investment - COALESCE(latest_report.accumulated_investment, 0) ELSE 0 END) > 0')
            ->orderByDesc('potential')
            ->orderBy('companies.name')
            ->limit(200)
            ->get();
    }

    public function show(Assignment $assignment, GapCalculator $calculator): View
    {
        abort_unless(auth()->user()->role === 'kepala_bagian' || $assignment->pic_id === auth()->id(), 403);
        $assignment->load(['company.projects.reports', 'pic', 'followUps.createdBy']);
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
