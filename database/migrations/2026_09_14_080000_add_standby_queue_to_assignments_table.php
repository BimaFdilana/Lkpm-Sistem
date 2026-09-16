<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('queue_type')->default('primary')->after('is_primary_target')->index();
            $table->boolean('is_task_active')->default(true)->after('queue_type')->index();
            $table->timestamp('activated_at')->nullable()->after('is_task_active');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['queue_type', 'is_task_active', 'activated_at']);
        });
    }
};
