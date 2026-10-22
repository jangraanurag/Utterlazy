<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public static function log(
        string $action,
        ?string $module = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $admin = Auth::user();
        if (! $admin || ! $admin->is_admin) {
            return;
        }

        $request = app(Request::class);

        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'admin_role' => $admin->admin_role,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
