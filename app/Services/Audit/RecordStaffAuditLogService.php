<?php

namespace App\Services\Audit;

use App\Models\StaffAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class RecordStaffAuditLogService
{
    /** @param array<string, mixed> $metadata */
    public function record(
        Request $request,
        string $eventType,
        ?User $actor = null,
        ?User $target = null,
        array $metadata = [],
    ): StaffAuditLog {
        return StaffAuditLog::create([
            'actor_user_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'event_type' => $eventType,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata ?: null,
        ]);
    }
}
