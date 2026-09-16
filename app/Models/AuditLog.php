<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'event', 'auditable_type', 'auditable_id', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
