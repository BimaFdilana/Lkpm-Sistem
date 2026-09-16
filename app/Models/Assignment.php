<?php

namespace App\Models;

use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'pic_id', 'assigned_by', 'year', 'quarter', 'status', 'priority_rank', 'is_primary_target', 'queue_type', 'is_task_active', 'activated_at', 'potential_at_assignment', 'reason', 'assigned_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'activated_at' => 'datetime', 'is_primary_target' => 'boolean', 'is_task_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
