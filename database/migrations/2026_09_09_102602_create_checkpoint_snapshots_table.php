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
        Schema::create('checkpoint_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_period_id')->constrained()->cascadeOnDelete();
            $table->date('checkpoint_at');
            $table->string('kind')->default('manual');
            $table->unsignedInteger('assigned_company_count')->default(0);
            $table->unsignedInteger('contacted_company_count')->default(0);
            $table->unsignedInteger('confirmed_company_count')->default(0);
            $table->unsignedBigInteger('indicated_amount')->default(0);
            $table->unsignedBigInteger('primary_target_coverage')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['target_period_id', 'checkpoint_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkpoint_snapshots');
    }
};
