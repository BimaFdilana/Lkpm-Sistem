<?php

namespace App\Http\Controllers;

use App\Models\LkpmReport;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegratedDataController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'in:all,lkpm,without_report'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:60'],
        ]);

        $reportFilter = function ($query) use ($filters): void {
            $query->where('is_canonical', true)
                ->when($filters['year'] ?? null, fn ($query, int $year) => $query->where('report_year', $year))
                ->when($filters['quarter'] ?? null, fn ($query, string $quarter) => $query->where('report_quarter', $quarter))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('report_status', $status));
        };

        $projects = Project::query()
            ->with('company')
            ->with(['reports' => fn ($query) => $reportFilter($query->orderByDesc('reported_at')->orderByDesc('id'))])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('project_code', 'like', "%{$search}%")
                        ->orWhere('kbli', 'like', "%{$search}%")
                        ->orWhereHas('company', fn (Builder $company) => $company
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('nib', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['source'] ?? 'all') === 'lkpm', fn (Builder $query) => $query->whereHas('reports', $reportFilter))
            ->when(($filters['source'] ?? 'all') === 'without_report', fn (Builder $query) => $query->whereDoesntHave('reports', fn (Builder $report) => $report->where('is_canonical', true)))
            ->orderBy('project_code')
            ->paginate(25)
            ->withQueryString();

        return view('integrated-data.index', [
            'projects' => $projects,
            'filters' => $filters,
            'years' => LkpmReport::query()->where('is_canonical', true)->distinct()->orderByDesc('report_year')->pluck('report_year'),
            'quarters' => LkpmReport::query()->where('is_canonical', true)->distinct()->orderBy('report_quarter')->pluck('report_quarter'),
            'statuses' => LkpmReport::query()->where('is_canonical', true)->distinct()->orderBy('report_status')->pluck('report_status'),
        ]);
    }

    public function show(Project $project): View
    {
        $project->load([
            'company',
            'reports' => fn ($query) => $query->where('is_canonical', true)->orderByDesc('reported_at')->orderByDesc('id'),
        ]);

        return view('integrated-data.show', ['project' => $project]);
    }
}
