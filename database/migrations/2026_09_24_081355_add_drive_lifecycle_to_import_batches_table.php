<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('drive_state')->default('local')->after('drive_file_id')->index();
            $table->text('drive_error')->nullable()->after('drive_state');
            $table->timestamp('drive_moved_at')->nullable()->after('drive_error');
        });

        DB::table('import_batches')->whereNotNull('drive_file_id')->update(['drive_state' => 'source']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['drive_state', 'drive_error', 'drive_moved_at']);
        });
    }
};
