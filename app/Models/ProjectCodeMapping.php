<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCodeMapping extends Model
{
    use HasFactory;

    protected $fillable = ['lkpm_project_code', 'project_id', 'mapped_by'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }
}
