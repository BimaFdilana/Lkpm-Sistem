<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'project_code', 'name', 'kbli', 'kbli_description', 'sector', 'project_stage', 'status', 'issued_at', 'planned_investment', 'planned_tki', 'source_payload'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'source_payload' => 'array'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(LkpmReport::class);
    }
}
