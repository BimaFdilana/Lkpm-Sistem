<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('priority_project_baselines', function (Blueprint $table): void {
            $table->id(); $table->foreignId('target_period_id')->constrained()->cascadeOnDelete(); $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('initial_accumulated_investment')->default(0); $table->unsignedBigInteger('historical_quarterly_realization')->nullable(); $table->timestamp('captured_at'); $table->timestamps();
            $table->unique(['target_period_id', 'project_id']);
        });
        Schema::table('daily_priority_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('remaining_target')->default(0)->after('remaining_potential');
            $table->decimal('priority_score', 18, 4)->nullable()->after('remaining_target');
        });
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->string('verification_status')->default('belum')->after('confirmation_status')->index();
        });
    }
    public function down(): void
    {
        Schema::table('follow_ups', fn (Blueprint $table) => $table->dropColumn('verification_status'));
        Schema::table('daily_priority_snapshots', fn (Blueprint $table) => $table->dropColumn(['remaining_target', 'priority_score']));
        Schema::dropIfExists('priority_project_baselines');
    }
};
