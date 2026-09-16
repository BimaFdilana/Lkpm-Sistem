<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_project_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('quarter');
            $table->foreignId('baseline_report_id')->nullable()->constrained('lkpm_reports')->nullOnDelete();
            $table->foreignId('ending_report_id')->nullable()->constrained('lkpm_reports')->nullOnDelete();
            $table->unsignedBigInteger('baseline_amount')->default(0);
            $table->unsignedBigInteger('ending_amount')->default(0);
            $table->bigInteger('momentum_amount')->nullable();
            $table->boolean('baseline_is_estimated')->default(true);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['project_id', 'year', 'quarter'], 'quarterly_baseline_project_period_unique');
            $table->index(['year', 'quarter'], 'quarterly_baseline_period_index');
        });

        Schema::create('daily_project_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lkpm_report_id')->nullable()->constrained('lkpm_reports')->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedSmallInteger('year');
            $table->string('quarter');
            $table->string('report_status')->nullable();
            $table->unsignedBigInteger('accumulated_investment')->default(0);
            $table->boolean('is_valid_realization')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'year', 'quarter', 'snapshot_date'], 'daily_snapshot_project_period_date_unique');
            $table->index(['snapshot_date', 'year', 'quarter'], 'daily_snapshot_date_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_project_snapshots');
        Schema::dropIfExists('quarterly_project_baselines');
    }
};
