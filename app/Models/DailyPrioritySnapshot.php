<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPrioritySnapshot extends Model
{
    protected $fillable = [
        'target_period_id', 'snapshot_date', 'project_id', 'company_id', 'import_batch_id',
        'planned_investment', 'valid_accumulated_investment', 'baseline_accumulated_investment',
        'valid_momentum', 'monitoring_accumulated_investment', 'monitoring_momentum', 'remaining_potential', 'remaining_target', 'priority_score',
        'pic_indicated_amount', 'historical_quarterly_realization', 'projected_contribution', 'projection_source',
        'lkpm_status', 'verification_status', 'candidate_tier', 'priority_rank', 'calculation_meta',
    ];

    protected function casts(): array
    {
        return ['snapshot_date' => 'date', 'calculation_meta' => 'array'];
    }

    public function period(): BelongsTo { return $this->belongsTo(TargetPeriod::class, 'target_period_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function importBatch(): BelongsTo { return $this->belongsTo(ImportBatch::class); }
}
