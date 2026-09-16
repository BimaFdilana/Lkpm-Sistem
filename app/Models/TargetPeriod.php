<?php

namespace App\Models;

use Database\Factories\TargetPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetPeriod extends Model
{
    /** @use HasFactory<TargetPeriodFactory> */
    use HasFactory;

    protected $fillable = ['annual_target_version_id', 'year', 'quarter', 'annual_target', 'target_amount', 'buffer_amount', 'baseline_realization', 'activity_starts_at', 'activity_ends_at', 'reporting_starts_at', 'reporting_ends_at', 'is_locked', 'is_closed', 'target_frozen_at', 'is_active', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['activity_starts_at' => 'date', 'activity_ends_at' => 'date', 'reporting_starts_at' => 'date', 'reporting_ends_at' => 'date', 'is_locked' => 'boolean', 'is_closed' => 'boolean', 'target_frozen_at' => 'datetime', 'is_active' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(CheckpointSnapshot::class);
    }
}
