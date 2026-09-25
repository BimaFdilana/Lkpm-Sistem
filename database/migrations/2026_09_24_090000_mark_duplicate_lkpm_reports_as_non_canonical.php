<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lkpm_reports')
            ->whereNotNull('report_number')
            ->selectRaw('project_code, report_number, report_year, report_quarter, MAX(id) as canonical_id')
            ->groupBy('project_code', 'report_number', 'report_year', 'report_quarter')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('canonical_id')
            ->each(function (object $duplicate): void {
                DB::table('lkpm_reports')
                    ->where('project_code', $duplicate->project_code)
                    ->where('report_number', $duplicate->report_number)
                    ->where('report_year', $duplicate->report_year)
                    ->where('report_quarter', $duplicate->report_quarter)
                    ->where('id', '!=', $duplicate->canonical_id)
                    ->update(['is_canonical' => false, 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        // Historical duplicates are retained for audit, so canonical status is not restored automatically.
    }
};
