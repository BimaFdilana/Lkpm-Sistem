<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_priority_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_period_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('planned_investment')->default(0);
            $table->unsignedBigInteger('valid_accumulated_investment')->default(0);
            $table->unsignedBigInteger('baseline_accumulated_investment')->default(0);
            $table->unsignedBigInteger('valid_momentum')->default(0);
            $table->unsignedBigInteger('monitoring_accumulated_investment')->default(0);
            $table->unsignedBigInteger('monitoring_momentum')->default(0);
            $table->unsignedBigInteger('remaining_potential')->default(0);
            $table->unsignedBigInteger('pic_indicated_amount')->nullable();
            $table->unsignedBigInteger('historical_quarterly_realization')->nullable();
            $table->unsignedBigInteger('projected_contribution')->nullable();
            $table->string('projection_source')->default('unknown');
            $table->string('lkpm_status')->nullable();
            $table->string('verification_status')->default('perlu_verifikasi');
            $table->string('candidate_tier')->default('amber')->index();
            $table->unsignedInteger('priority_rank')->nullable()->index();
            $table->json('calculation_meta')->nullable();
            $table->timestamps();
            $table->unique(['target_period_id', 'snapshot_date', 'project_id'], 'daily_priority_snapshot_once');
            $table->index(['target_period_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_priority_snapshots');
    }
};
