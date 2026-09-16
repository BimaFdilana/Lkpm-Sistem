<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProjectSnapshot extends Model
{
    protected $fillable = ['project_id', 'import_batch_id', 'lkpm_report_id', 'snapshot_date', 'year', 'quarter', 'report_status', 'accumulated_investment', 'is_valid_realization'];

    protected function casts(): array
    {
        return ['snapshot_date' => 'date', 'is_valid_realization' => 'boolean'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function importBatch(): BelongsTo { return $this->belongsTo(ImportBatch::class); }
    public function report(): BelongsTo { return $this->belongsTo(LkpmReport::class, 'lkpm_report_id'); }
}
