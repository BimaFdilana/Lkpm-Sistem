<?php

namespace App\Models;

use Database\Factories\LkpmReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LkpmReport extends Model
{
    /** @use HasFactory<LkpmReportFactory> */
    use HasFactory;

    protected $fillable = ['import_batch_id', 'project_id', 'project_code', 'report_number', 'report_year', 'report_quarter', 'reported_at', 'report_status', 'total_investment_plan', 'additional_investment', 'accumulated_investment', 'accumulated_fixed_capital', 'capital_explanation', 'planned_tki', 'realized_tki', 'planned_tka', 'realized_tka', 'is_canonical', 'source_payload'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'is_canonical' => 'boolean', 'source_payload' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
