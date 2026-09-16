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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('source_type')->index();
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('drive_file_id')->nullable();
            $table->string('checksum', 64)->index();
            $table->unsignedSmallInteger('report_year')->nullable();
            $table->string('report_quarter')->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
