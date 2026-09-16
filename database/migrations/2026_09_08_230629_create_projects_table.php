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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('project_code')->unique();
            $table->string('name')->nullable();
            $table->string('kbli', 16)->nullable()->index();
            $table->string('kbli_description')->nullable();
            $table->string('sector')->nullable()->index();
            $table->string('project_stage')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('issued_at')->nullable();
            $table->unsignedBigInteger('planned_investment')->default(0);
            $table->unsignedInteger('planned_tki')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
