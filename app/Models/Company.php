<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = ['nib', 'name', 'investment_status', 'business_scale', 'address', 'district', 'subdistrict', 'contact_name', 'contact_phone', 'contact_email', 'contact_position', 'contact_source_report_id', 'contact_synced_at', 'source_payload'];

    protected function casts(): array
    {
        return ['source_payload' => 'array', 'contact_synced_at' => 'datetime'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function contactSourceReport(): BelongsTo
    {
        return $this->belongsTo(LkpmReport::class, 'contact_source_report_id');
    }
}
