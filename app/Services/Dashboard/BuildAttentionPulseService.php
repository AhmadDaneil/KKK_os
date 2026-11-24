<?php

namespace App\Services\Dashboard;

use App\Models\DesignJob;
use App\Models\Order;
use App\Models\PrintJob;

class BuildAttentionPulseService
{
    public function hasOverdueDesignTasks(?int $assignedUserId = null): bool
    {
        $deadlineStart = now()->startOfDay()->subDays(3)->toDateString();

        return DesignJob::query()
            ->whereIn('status', ['READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS', 'CORRECTION_REQUESTED'])
            ->when($assignedUserId !== null, fn ($query) => $query->where('assigned_user_id', $assignedUserId))
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
            ->where(function ($query) use ($deadlineStart): void {
                $query->whereHas('order.statusEvents', function ($events) use ($deadlineStart): void {
                    $events->where('to_status', 'READY_FOR_DESIGN')
                        ->whereDate('occurred_at', '<=', $deadlineStart);
                })->orWhere(function ($fallback) use ($deadlineStart): void {
                    $fallback->whereDoesntHave('order.statusEvents', fn ($events) => $events->where('to_status', 'READY_FOR_DESIGN'))
                        ->whereDate('created_at', '<=', $deadlineStart);
                });
            })
            ->exists();
    }

    public function hasOverdueProductionTasks(): bool
    {
        $deadlineStart = now()->startOfDay()->subDays(7)->toDateString();

        return PrintJob::query()
            ->whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
            ->where(function ($query) use ($deadlineStart): void {
                $query->whereHas('events', function ($events) use ($deadlineStart): void {
                    $events->where('to_status', 'READY_FOR_PRINT')
                        ->whereDate('occurred_at', '<=', $deadlineStart);
                })->orWhere(function ($fallback) use ($deadlineStart): void {
                    $fallback->whereDoesntHave('events', fn ($events) => $events->where('to_status', 'READY_FOR_PRINT'))
                        ->whereDate('created_at', '<=', $deadlineStart);
                });
            })
            ->exists();
    }
}
