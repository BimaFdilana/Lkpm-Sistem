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
        Schema::create('target_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('quarter');
            $table->unsignedBigInteger('target_amount');
            $table->unsignedBigInteger('baseline_realization')->default(0);
            $table->date('activity_starts_at')->nullable();
            $table->date('activity_ends_at')->nullable();
            $table->date('reporting_starts_at')->nullable();
            $table->date('reporting_ends_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->unique(['year', 'quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_periods');
    }
};
