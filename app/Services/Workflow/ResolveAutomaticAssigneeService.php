<?php

namespace App\Services\Workflow;

use App\Models\User;
use RuntimeException;

class ResolveAutomaticAssigneeService
{
    public function designLead(): User
    {
        return $this->activeStaff(User::ROLE_DESIGNER)
            ->orderByDesc('is_design_lead')
            ->orderBy('id')
            ->first()
            ?? throw new RuntimeException('Sila aktifkan sekurang-kurangnya seorang pereka untuk menerima tugasan design automatik.');
    }

    public function correctionDesigner(?int $previousDesignerId): User
    {
        $backupDesigner = $this->activeStaff(User::ROLE_DESIGNER)
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
            ?? throw new RuntimeException('Sila aktifkan seorang staf pengeluaran untuk menerima tugasan cetakan automatik.');
    }

    private function activeStaff(string $role)
    {
        return User::query()
            ->where('role', $role)
            ->where('is_active', true);
    }
}
