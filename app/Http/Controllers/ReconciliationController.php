<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\Http\Requests\StoreProjectCodeMappingRequest;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\ProjectCodeMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(): View
    {
        return view('reconciliations.index', [
            'unlinkedReportCount' => LkpmReport::query()->whereNull('project_id')->count(),
            'unlinkedCodes' => LkpmReport::query()
                ->whereNull('project_id')
                ->select('project_code')
                ->selectRaw('count(*) as report_count')
                ->groupBy('project_code')
                ->orderByDesc('report_count')
                ->orderBy('project_code')
                ->paginate(30),
            'recentMappings' => ProjectCodeMapping::query()->with(['project.company', 'mappedBy'])->latest()->limit(10)->get(),
        ]);
    }

    public function store(StoreProjectCodeMappingRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $project = Project::query()->where('project_code', $request->string('project_code')->toString())->sole();

        $mapping = DB::transaction(function () use ($request, $project): ProjectCodeMapping {
            $mapping = ProjectCodeMapping::query()->updateOrCreate(
                ['lkpm_project_code' => $request->string('lkpm_project_code')->toString()],
                ['project_id' => $project->id, 'mapped_by' => $request->user()->id],
            );
            LkpmReport::query()
                ->where('project_code', $mapping->lkpm_project_code)
                ->update(['project_id' => $project->id]);

            return $mapping;
        });
        $auditLogger->log($request->user(), 'project_code_mapped', $mapping, ['project_code' => $project->project_code]);

        return back()->with('status', 'Kode LKPM berhasil dipetakan ke proyek DP.Proyek.');
    }
}
