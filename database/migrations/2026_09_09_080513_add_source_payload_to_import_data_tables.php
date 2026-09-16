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
        Schema::table('companies', function (Blueprint $table): void {
            $table->json('source_payload')->nullable()->after('contact_position');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->json('source_payload')->nullable()->after('planned_tki');
        });

        Schema::table('lkpm_reports', function (Blueprint $table): void {
            $table->json('source_payload')->nullable()->after('is_canonical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lkpm_reports', function (Blueprint $table): void {
            $table->dropColumn('source_payload');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('source_payload');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('source_payload');
        });
    }
};
