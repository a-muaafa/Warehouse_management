<?php

namespace App\Policies;

use App\Models\InventoryAudit;
use App\Models\User;

class InventoryAuditPolicy
{
    /**
     * Determine whether the user can view any audits.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audits.view');
    }

    /**
     * Determine whether the user can view the audit.
     */
    public function view(User $user, InventoryAudit $audit): bool
    {
        if (! $user->hasPermission('audits.view')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($audit->warehouse_id && ! $user->canAccessWarehouse($audit->warehouse_id)) {
            return false;
        }

        if ($user->department_id && $audit->department_id && $audit->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create audits.
     */
    public function create(User $user, ?int $warehouseId = null): bool
    {
        if (! $user->hasPermission('audits.create')) {
            return false;
        }

        if ($warehouseId && ! $user->canAccessWarehouse($warehouseId)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update counts for the audit.
     * Prevents user from modifying an audit they don't have access to or modifying reconciled audits.
     */
    public function count(User $user, InventoryAudit $audit): bool
    {
        if ($audit->status === 'reconciled') {
            return false;
        }

        if (! $user->hasPermission('audits.count')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($audit->warehouse_id && ! $user->canAccessWarehouse($audit->warehouse_id)) {
            return false;
        }

        if ($user->department_id && $audit->department_id && $audit->department_id !== $user->department_id) {
            return false;
        }

        // If not manager/admin, user can only count audits they initiated/are assigned to
        if ($user->isStockCounter() && $audit->audited_by !== $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can approve / reconcile the audit.
     */
    public function approve(User $user, InventoryAudit $audit): bool
    {
        if ($audit->status === 'reconciled') {
            return false;
        }

        if (! $user->hasPermission('audits.approve')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($audit->warehouse_id && ! $user->canAccessWarehouse($audit->warehouse_id)) {
            return false;
        }

        if ($user->department_id && $audit->department_id && $audit->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }
}
