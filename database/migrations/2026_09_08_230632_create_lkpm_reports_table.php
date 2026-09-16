<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lkpm_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_code')->index();
            $table->string('report_number')->nullable();
            $table->unsignedSmallInteger('report_year')->index();
            $table->string('report_quarter')->index();
            $table->timestamp('reported_at')->nullable();
            $table->string('report_status')->index();
            $table->unsignedBigInteger('total_investment_plan')->default(0);
            $table->unsignedBigInteger('additional_investment')->default(0);
            $table->unsignedBigInteger('accumulated_investment')->default(0);
            $table->unsignedBigInteger('accumulated_fixed_capital')->default(0);
            $table->text('capital_explanation')->nullable();
            $table->unsignedInteger('planned_tki')->default(0);
            $table->unsignedInteger('realized_tki')->default(0);
            $table->unsignedInteger('planned_tka')->default(0);
            $table->unsignedInteger('realized_tka')->default(0);
            $table->boolean('is_canonical')->default(false)->index();
            $table->timestamps();
            $table->index(['project_code', 'report_year', 'report_quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lkpm_reports');
    }
};
