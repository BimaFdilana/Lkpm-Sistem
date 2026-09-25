<?php

namespace App\Http\Controllers;

use App\AuditLogger;
use App\CompanyContactSynchronizer;
use App\Http\Requests\StoreProjectCodeMappingRequest;
use App\Models\Company;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\ProjectCodeMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $filteredReports = $this->unlinkedReports($request);
        $unlinkedCodes = (clone $filteredReports)
            ->select('project_code')
            ->selectRaw('count(*) as report_count')
            ->selectRaw('min(report_year) as first_year, max(report_year) as last_year')
            ->groupBy('project_code')
            ->orderByDesc('report_count')
            ->orderBy('project_code')
            ->paginate(30)
            ->withQueryString();
        $existingProjectCodes = Project::query()
            ->whereIn('project_code', $unlinkedCodes->getCollection()->pluck('project_code'))
            ->pluck('project_code')
            ->flip();
        $unlinkedCodes->setCollection($unlinkedCodes->getCollection()->map(function ($row) use ($existingProjectCodes) {
            $row->reason = $existingProjectCodes->has($row->project_code)
                ? 'Kode cocok; perlu relink otomatis'
                : 'Kode belum tersedia di DP.Proyek';

            return $row;
        }));

        return view('reconciliations.index', [
            'unlinkedReportCount' => LkpmReport::query()->where('is_canonical', true)->whereNull('project_id')->count(),
            'unlinkedCodeCount' => LkpmReport::query()->where('is_canonical', true)->whereNull('project_id')->distinct()->count('project_code'),
            'unlinkedCodes' => $unlinkedCodes,
            'availableYears' => LkpmReport::query()->where('is_canonical', true)->whereNull('project_id')->distinct()->orderByDesc('report_year')->pluck('report_year'),
            'missingEmailCount' => Company::query()->where(fn ($query) => $query->whereNull('contact_email')->orWhere('contact_email', ''))->count(),
            'missingPhoneCount' => Company::query()->where(fn ($query) => $query->whereNull('contact_phone')->orWhere('contact_phone', ''))->count(),
            'missingSectorCount' => Project::query()->where(fn ($query) => $query->whereNull('sector')->orWhere('sector', ''))->count(),
            'recentMappings' => ProjectCodeMapping::query()->with(['project.company', 'mappedBy'])->latest()->limit(10)->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->unlinkedReports($request)
            ->select('project_code')
            ->selectRaw('count(*) as report_count')
            ->selectRaw('min(report_year) as first_year, max(report_year) as last_year')
            ->groupBy('project_code')
            ->orderByDesc('report_count')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['NO KODE PROYEK LKPM', 'JUMLAH LAPORAN', 'TAHUN AWAL', 'TAHUN AKHIR', 'STATUS']);
            foreach ($rows as $row) {
                fputcsv($output, [$row->project_code, $row->report_count, $row->first_year, $row->last_year, 'Kode belum tersedia di DP.Proyek']);
            }
            fclose($output);
        }, 'rekonsiliasi-kode-lkpm-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreProjectCodeMappingRequest $request, AuditLogger $auditLogger, CompanyContactSynchronizer $companyContactSynchronizer): RedirectResponse
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
        $companyContactSynchronizer->sync();

        return back()->with('status', 'Kode LKPM berhasil dipetakan ke proyek DP.Proyek dan kontak perusahaan disinkronkan otomatis.');
    }

    private function unlinkedReports(Request $request): Builder
    {
        return LkpmReport::query()
            ->where('is_canonical', true)
            ->whereNull('project_id')
            ->when($request->filled('year'), fn (Builder $query) => $query->where('report_year', $request->integer('year')))
            ->when($request->filled('quarter'), fn (Builder $query) => $query->where('report_quarter', $request->string('quarter')->toString()))
            ->when($request->filled('code'), fn (Builder $query) => $query->where('project_code', 'like', '%'.$request->string('code')->trim()->toString().'%'));
    }
}
