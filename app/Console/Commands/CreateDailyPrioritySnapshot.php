<?php

namespace App\Console\Commands;

use App\DailyPriorityEngine;
use App\Models\DailyPrioritySnapshot;
use App\Models\TargetPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateDailyPrioritySnapshot extends Command
{
    protected $signature = 'priority:snapshot {--date= : Tanggal snapshot (YYYY-MM-DD), default hari ini}';

    protected $description = 'Kunci snapshot simulasi prioritas harian setelah seluruh impor pagi selesai';

    public function handle(DailyPriorityEngine $engine): int
    {
        $period = TargetPeriod::query()->where('is_active', true)->first();
        if ($period === null) {
            $this->error('Tidak ada periode kerja aktif.');
            return self::FAILURE;
        }
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $exists = DailyPrioritySnapshot::query()->where('target_period_id', $period->id)->whereDate('snapshot_date', $date)->exists();
        $rows = $engine->snapshot($period, $date);
        $this->info($exists ? 'Snapshot sudah terkunci; tidak ada data yang diubah.' : "Snapshot {$date->toDateString()} dibuat ({$rows->count()} proyek).");

        return self::SUCCESS;
    }
}
