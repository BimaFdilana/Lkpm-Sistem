<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualTargetVersion extends Model
{
    protected $fillable = ['year', 'annual_target', 'quarter_distribution', 'reason', 'effective_at', 'created_by'];
    protected function casts(): array { return ['quarter_distribution' => 'array', 'effective_at' => 'datetime']; }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
