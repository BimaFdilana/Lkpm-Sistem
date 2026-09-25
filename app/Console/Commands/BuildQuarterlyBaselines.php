<?php

namespace App\Console\Commands;

use App\QuarterlyBaselineBuilder;
use Illuminate\Console\Command;

class BuildQuarterlyBaselines extends Command
{
    protected $signature = 'lkpm:build-quarterly-baselines {--from=2021} {--to=2026}';

    protected $description = 'Membangun ulang baseline dan momentum LKPM per proyek untuk periode historis.';

    public function handle(QuarterlyBaselineBuilder $builder): int
    {
        $count = $builder->build((int) $this->option('from'), (int) $this->option('to'));
        $this->info("Baseline historis diperbarui untuk {$count} proyek-triwulan.");

        return self::SUCCESS;
    }
}
