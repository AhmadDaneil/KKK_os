<?php

namespace App\Services\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ResolveAutomaticAssigneeService
{
    public function designLead(): User
    {
        return $this->activeStaff(User::DESIGNER_ROLES)
            ->orderByDesc('is_design_lead')
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException('Please activate at least one designer to receive automatically assigned design jobs.');
    }

    public function correctionDesigner(?int $previousDesignerId): User
    {
        $backupDesigner = $this->activeStaff(User::DESIGNER_ROLES)
            ->when($previousDesignerId, fn ($query) => $query->where('id', '!=', $previousDesignerId))
            ->orderBy('is_design_lead')
            ->orderBy('id')
            ->first();

        return $backupDesigner ?? $this->designLead();
    }

    public function productionStaff(): User
    {
        return $this->activeStaff(User::ROLE_PRODUCTION)
            ->orderByDesc('is_primary_production')
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException('Please activate a production staff member to receive automatically assigned print jobs.');
    }

    private function activeStaff(string|array $roles): Builder
    {
        return User::query()
            ->whereIn('role', (array) $roles)
            ->where('is_active', true);
    }
}
