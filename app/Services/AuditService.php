<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function log(string $action, Model $entity, array $old = [], array $new = [], ?string $description = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(), 'action' => $action, 'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(), 'description' => $description,
            'old_values' => $old ?: null, 'new_values' => $new ?: null,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
    }
}
