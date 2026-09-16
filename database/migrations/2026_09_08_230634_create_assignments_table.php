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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pic_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->unsignedSmallInteger('year');
            $table->string('quarter');
            $table->string('status')->default('active')->index();
            $table->text('reason')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->index(['pic_id', 'year', 'quarter', 'status']);
            $table->index(['company_id', 'year', 'quarter', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
