<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected $fillable = ['uploaded_by', 'source_type', 'original_name', 'disk', 'path', 'drive_file_id', 'drive_state', 'drive_error', 'drive_moved_at', 'checksum', 'report_year', 'report_quarter', 'status', 'accepted_rows', 'rejected_rows', 'summary', 'activated_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'activated_at' => 'datetime', 'drive_moved_at' => 'datetime'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
