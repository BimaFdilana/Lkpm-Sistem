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
        Schema::table('target_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('annual_target')->default(0)->after('quarter');
            $table->unsignedBigInteger('buffer_amount')->default(0)->after('target_amount');
            $table->boolean('is_active')->default(false)->after('is_locked')->index();
            $table->foreignId('approved_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->unsignedInteger('priority_rank')->nullable()->after('status')->index();
            $table->boolean('is_primary_target')->default(false)->after('priority_rank')->index();
            $table->unsignedBigInteger('potential_at_assignment')->default(0)->after('is_primary_target');
        });

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->string('contact_status')->default('belum_dihubungi')->after('status')->index();
            $table->string('confirmation_status')->default('belum_terkonfirmasi')->after('contact_status')->index();
            $table->unsignedBigInteger('indicated_amount')->nullable()->after('confirmation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->dropColumn(['contact_status', 'confirmation_status', 'indicated_amount']);
        });
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['priority_rank', 'is_primary_target', 'potential_at_assignment']);
        });
        Schema::table('target_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['annual_target', 'buffer_amount', 'is_active', 'approved_at']);
        });
    }
};
