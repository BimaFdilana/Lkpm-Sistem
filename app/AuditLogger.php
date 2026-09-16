<?php

namespace App;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(?User $user, string $event, Model $model, array $context = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'context' => $context,
        ]);
    }
}
