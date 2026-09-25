<?php

namespace App;

use App\Models\DailyProjectSnapshot;
use App\Models\ImportBatch;
use App\Models\LkpmReport;

class DailySnapshotRecorder
{
    public function record(ImportBatch $batch): int
    {
        $reports = LkpmReport::query()->where('import_batch_id', $batch->id)->whereNotNull('project_id')->get()
            ->groupBy(fn (LkpmReport $report) => $report->project_id.'|'.$report->report_year.'|'.$report->report_quarter)
            ->map(fn ($items) => $items->sortBy(fn (LkpmReport $report) => [$report->reported_at?->getTimestamp() ?? 0, $report->id])->last());
        $date = now()->toDateString();
        $rows = $reports->map(fn (LkpmReport $report) => [
            'project_id' => $report->project_id,
            'import_batch_id' => $batch->id,
            'lkpm_report_id' => $report->id,
            'snapshot_date' => $date,
            'year' => $report->report_year,
            'quarter' => $report->report_quarter,
            'report_status' => $report->report_status,
            'accumulated_investment' => $report->accumulated_investment,
            'is_valid_realization' => $report->report_status === 'Disetujui',
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DailyProjectSnapshot::query()->upsert($chunk, ['project_id', 'year', 'quarter', 'snapshot_date'], ['import_batch_id', 'lkpm_report_id', 'report_status', 'accumulated_investment', 'is_valid_realization', 'updated_at']);
        }

        return count($rows);
    }
}
