<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseTransfer;

class WarehouseTransferPolicy
{
    /**
     * Determine whether the user can view any transfers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transfers.view');
    }

    /**
     * Determine whether the user can view the transfer.
     */
    public function view(User $user, WarehouseTransfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.view')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessWarehouse($transfer->from_warehouse_id)
            || $user->canAccessWarehouse($transfer->to_warehouse_id)
            || $transfer->requested_by === $user->id;
    }

    /**
     * Determine whether the user can create transfers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('transfers.create');
    }

    /**
     * Determine whether the user can dispatch the transfer from source warehouse.
     */
    public function dispatch(User $user, WarehouseTransfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.dispatch')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessWarehouse($transfer->from_warehouse_id);
    }

    /**
     * Determine whether the user can receive the transfer at destination warehouse.
     */
    public function receive(User $user, WarehouseTransfer $transfer): bool
    {
        if (! $user->hasPermission('transfers.receive')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessWarehouse($transfer->to_warehouse_id);
    }

    /**
     * Determine whether the user can resolve transfer discrepancies.
     */
    public function resolve(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->hasPermission('transfers.resolve');
    }

    /**
     * Determine whether the user can cancel the transfer.
     */
    public function cancel(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->isSuperAdmin() || ($user->hasPermission('transfers.create') && $user->canAccessWarehouse($transfer->from_warehouse_id));
    }
}
