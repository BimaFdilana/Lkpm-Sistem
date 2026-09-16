<?php

namespace App;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LkpmReport;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class ImportDataProcessor
{
    public function __construct(private CompanyContactSynchronizer $companyContactSynchronizer, private DailySnapshotRecorder $dailySnapshotRecorder, private QuarterlyBaselineBuilder $quarterlyBaselineBuilder) {}

    /**
     * @return array{accepted_rows: int, rejected_rows: int, unlinked_reports?: int}
     */
    public function process(ImportBatch $batch): array
    {
        $normalizedPath = 'imports/normalized/batch-'.$batch->id.'.jsonl';
        Storage::makeDirectory('imports/normalized');

        $result = Process::path(base_path())
            ->timeout(900)
            ->run([
                'python3',
                'python/import_processor.py',
                $batch->source_type,
                Storage::path($batch->path),
                Storage::path($normalizedPath),
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Worker Python gagal memproses file impor.');
        }

        try {
            $summary = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Worker Python tidak mengembalikan ringkasan impor yang valid.');
        }

        if (! is_array($summary) || ! isset($summary['accepted_rows'], $summary['rejected_rows'])) {
            throw new RuntimeException('Ringkasan impor tidak lengkap.');
        }

        try {
            $resultSummary = match ($batch->source_type) {
                'projects' => $this->storeProjects($normalizedPath),
                'lkpm' => $this->storeLkpmReports($batch, $normalizedPath),
                'sectors' => $this->storeSectors($normalizedPath),
                default => throw new RuntimeException('Jenis data impor tidak dikenali.'),
            };

            if ($batch->source_type === 'lkpm') {
                $resultSummary['contacts_updated'] = $this->companyContactSynchronizer->sync();
                $resultSummary['daily_snapshots_recorded'] = $this->dailySnapshotRecorder->record($batch);
                $projectIds = LkpmReport::query()->where('import_batch_id', $batch->id)->whereNotNull('project_id')->pluck('project_id')->unique()->all();
                $resultSummary['historical_baselines_updated'] = $this->quarterlyBaselineBuilder->build(2021, 2026, $projectIds);
            }
        } finally {
            Storage::delete($normalizedPath);
        }

        return [
            'accepted_rows' => (int) $summary['accepted_rows'],
            'rejected_rows' => (int) $summary['rejected_rows'] + ($resultSummary['rejected_rows'] ?? 0),
            ...$resultSummary,
        ];
    }

    /**
     * @return array{rejected_rows: int}
     */
    private function storeProjects(string $normalizedPath): array
    {
        foreach ($this->chunks($normalizedPath) as $rows) {
            DB::transaction(function () use ($rows): void {
                $now = now();
                $companies = [];
                foreach ($rows as $row) {
                    $companies[$row['nib']] = [
                        'nib' => $row['nib'],
                        'name' => $row['company_name'],
                        'investment_status' => $row['investment_status'],
                        'business_scale' => $row['business_scale'],
                        'address' => $row['address'],
                        'district' => $row['district'],
                        'subdistrict' => $row['subdistrict'],
                        'source_payload' => json_encode($row['raw'], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Company::query()->upsert(array_values($companies), ['nib'], ['name', 'investment_status', 'business_scale', 'address', 'district', 'subdistrict', 'source_payload', 'updated_at']);
                $companyIds = Company::query()->whereIn('nib', array_keys($companies))->pluck('id', 'nib');
                $projects = [];

                foreach ($rows as $row) {
                    $projects[] = [
                        'company_id' => $companyIds[$row['nib']],
                        'project_code' => $row['project_code'],
                        'name' => $row['project_name'],
                        'kbli' => $row['kbli'],
                        'kbli_description' => $row['kbli_description'],
                        'sector' => $row['sector'],
                        'project_stage' => $row['project_stage'],
                        'issued_at' => $this->dateValue($row['issued_at']),
                        'planned_investment' => $row['planned_investment'],
                        'planned_tki' => $row['planned_tki'],
                        'source_payload' => json_encode($row['raw'], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Project::query()->upsert($projects, ['project_code'], ['company_id', 'name', 'kbli', 'kbli_description', 'sector', 'project_stage', 'issued_at', 'planned_investment', 'planned_tki', 'source_payload', 'updated_at']);
            });
        }

        return ['rejected_rows' => 0];
    }

    /**
     * @return array{rejected_rows: int, unlinked_reports: int}
     */
    private function storeLkpmReports(ImportBatch $batch, string $normalizedPath): array
    {
        LkpmReport::query()->where('import_batch_id', $batch->id)->delete();
        $unlinkedReports = 0;

        foreach ($this->chunks($normalizedPath) as $rows) {
            $projectIds = Project::query()->whereIn('project_code', collect($rows)->pluck('project_code')->unique())->pluck('id', 'project_code');
            $now = now();
            $reports = [];

            foreach ($rows as $row) {
                if (! isset($projectIds[$row['project_code']])) {
                    $unlinkedReports++;
                }

                $reports[] = [
                    'import_batch_id' => $batch->id,
                    'project_id' => $projectIds[$row['project_code']] ?? null,
                    'project_code' => $row['project_code'],
                    'report_number' => $row['report_number'],
                    'report_year' => $row['report_year'],
                    'report_quarter' => $row['report_quarter'],
                    'reported_at' => $row['reported_at'],
                    'report_status' => $row['report_status'],
                    'total_investment_plan' => $row['total_investment_plan'],
                    'additional_investment' => $row['additional_investment'],
                    'accumulated_investment' => $row['accumulated_investment'],
                    'accumulated_fixed_capital' => $row['accumulated_fixed_capital'],
                    'capital_explanation' => $row['capital_explanation'],
                    'planned_tki' => $row['planned_tki'],
                    'realized_tki' => $row['realized_tki'],
                    'planned_tka' => $row['planned_tka'],
                    'realized_tka' => $row['realized_tka'],
                    'is_canonical' => true,
                    'source_payload' => json_encode($row['raw'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            LkpmReport::query()->insert($reports);
        }

        return ['rejected_rows' => 0, 'unlinked_reports' => $unlinkedReports];
    }

    /**
     * @return array{rejected_rows: int}
     */
    private function storeSectors(string $normalizedPath): array
    {
        foreach ($this->chunks($normalizedPath) as $rows) {
            $now = now();
            $sectors = array_map(fn (array $row): array => [
                'name' => $row['sector'],
                'kbli_count' => $row['kbli_count'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows);
            Sector::query()->upsert($sectors, ['name'], ['kbli_count', 'updated_at']);
        }

        return ['rejected_rows' => 0];
    }

    /**
     * @return \Generator<int, array<int, array<string, mixed>>>
     */
    private function chunks(string $normalizedPath): \Generator
    {
        $handle = fopen(Storage::path($normalizedPath), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Hasil normalisasi data tidak dapat dibaca.');
        }

        try {
            $rows = [];
            while (($line = fgets($handle)) !== false) {
                $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (count($rows) === 500) {
                    yield $rows;
                    $rows = [];
                }
            }
            if ($rows !== []) {
                yield $rows;
            }
        } finally {
            fclose($handle);
        }
    }

    private function dateValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return date('Y-m-d', strtotime($value)) ?: null;
    }
}
