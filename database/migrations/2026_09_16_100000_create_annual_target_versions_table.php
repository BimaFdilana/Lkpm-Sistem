<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annual_target_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedBigInteger('annual_target');
            $table->json('quarter_distribution');
            $table->text('reason');
            $table->timestamp('effective_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['year', 'effective_at']);
        });
        Schema::table('target_periods', function (Blueprint $table): void {
            $table->foreignId('annual_target_version_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_closed')->default(false)->after('is_locked')->index();
            $table->timestamp('target_frozen_at')->nullable()->after('is_closed');
        });
    }
    public function down(): void
    {
        Schema::table('target_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('annual_target_version_id');
            $table->dropColumn(['is_closed', 'target_frozen_at']);
        });
        Schema::dropIfExists('annual_target_versions');
    }
};
