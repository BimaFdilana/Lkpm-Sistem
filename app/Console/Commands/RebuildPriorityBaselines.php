<?php

namespace App\Console\Commands;

use App\DailyPriorityEngine;
use App\Models\PriorityProjectBaseline;
use App\Models\TargetPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('priority:rebuild-baselines {--year=} {--quarter=} {--force : Konfirmasi pembangunan ulang data turunan}')]
#[Description('Bangun ulang baseline prioritas periode tanpa menghapus snapshot historis')]
class RebuildPriorityBaselines extends Command
{
    public function handle(DailyPriorityEngine $engine): int
    {
        if (! $this->option('force')) {
            $this->error('Gunakan --force setelah backup database dikonfirmasi.');

            return self::FAILURE;
        }

        $period = TargetPeriod::query()
            ->when($this->option('year'), fn ($query) => $query->where('year', (int) $this->option('year')))
            ->when($this->option('quarter'), fn ($query) => $query->where('quarter', $this->option('quarter')))
            ->when(! $this->option('year') && ! $this->option('quarter'), fn ($query) => $query->where('is_active', true))
            ->first();

        if ($period === null) {
            $this->error('Periode target tidak ditemukan.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($period, $engine): void {
            PriorityProjectBaseline::query()->where('target_period_id', $period->id)->delete();
            $engine->captureBaselines($period);
        });

        $count = PriorityProjectBaseline::query()->where('target_period_id', $period->id)->count();
        $this->info("Baseline {$period->year} {$period->quarter} dibangun ulang untuk {$count} proyek. Snapshot historis tidak dihapus.");

        return self::SUCCESS;
    }
}
