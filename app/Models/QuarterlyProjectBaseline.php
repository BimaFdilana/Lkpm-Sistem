<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyProjectBaseline extends Model
{
    protected $fillable = ['project_id', 'year', 'quarter', 'baseline_report_id', 'ending_report_id', 'baseline_amount', 'ending_amount', 'momentum_amount', 'baseline_is_estimated', 'calculated_at'];

    protected function casts(): array
    {
        return ['baseline_is_estimated' => 'boolean', 'calculated_at' => 'datetime'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function baselineReport(): BelongsTo { return $this->belongsTo(LkpmReport::class, 'baseline_report_id'); }
    public function endingReport(): BelongsTo { return $this->belongsTo(LkpmReport::class, 'ending_report_id'); }
}
