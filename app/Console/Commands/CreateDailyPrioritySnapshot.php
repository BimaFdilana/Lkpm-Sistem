<?php

namespace App\Console\Commands;

use App\DailyPriorityEngine;
use App\Models\DailyPrioritySnapshot;
use App\Models\ImportBatch;
use App\Models\Setting;
use App\Models\TargetPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateDailyPrioritySnapshot extends Command
{
    protected $signature = 'priority:snapshot {--date= : Tanggal snapshot (YYYY-MM-DD), default hari ini} {--scheduled : Jalankan hanya setelah waktu snapshot yang dikonfigurasi}';

    protected $description = 'Kunci snapshot simulasi prioritas harian setelah seluruh impor pagi selesai';

    public function handle(DailyPriorityEngine $engine): int
    {
        $snapshotTime = Setting::query()->where('key', 'priority.snapshot_time')->value('value') ?: config('lkpm.priority_snapshot_time');
        if ($this->option('scheduled') && now()->format('H:i') < $snapshotTime) {
            return self::SUCCESS;
        }

        $period = TargetPeriod::query()->where('is_active', true)->first();
        if ($period === null) {
            $this->error('Tidak ada periode kerja aktif.');

            return self::FAILURE;
        }

        if (ImportBatch::query()->where('status', 'processing')->exists()) {
            $this->warn('Snapshot ditunda karena masih ada impor yang sedang diproses.');

            return self::SUCCESS;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $exists = DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', $date)->exists();
        $latestLkpmBatch = ImportBatch::query()->where('source_type', 'lkpm')->where('status', 'ready')->latest('id')->first();
        $rows = $engine->snapshot($period, $date, $latestLkpmBatch?->id);
        $this->info($exists ? 'Snapshot sudah terkunci; tidak ada data yang diubah.' : "Snapshot {$date->toDateString()} dibuat ({$rows->count()} proyek).");

        return self::SUCCESS;
    }
}
