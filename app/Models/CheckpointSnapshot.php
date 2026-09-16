<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckpointSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['target_period_id', 'checkpoint_at', 'kind', 'assigned_company_count', 'contacted_company_count', 'confirmed_company_count', 'indicated_amount', 'primary_target_coverage', 'created_by', 'summary'];

    protected function casts(): array
    {
        return ['checkpoint_at' => 'date', 'summary' => 'array'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TargetPeriod::class, 'target_period_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
