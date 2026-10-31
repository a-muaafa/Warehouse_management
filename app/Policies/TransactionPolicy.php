<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transactions.view');
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        if (! $user->hasPermission('transactions.view')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($transaction->warehouse_id && ! $user->canAccessWarehouse($transaction->warehouse_id)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can record an inbound transaction.
     */
    public function receive(User $user, ?int $warehouseId = null): bool
    {
        if (! $user->hasPermission('stock.receive')) {
            return false;
        }

        if ($warehouseId && ! $user->canAccessWarehouse($warehouseId)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can record an outbound transaction.
     */
    public function issue(User $user, ?int $warehouseId = null): bool
    {
        if (! $user->hasPermission('stock.issue')) {
            return false;
        }

        if ($warehouseId && ! $user->canAccessWarehouse($warehouseId)) {
            return false;
        }

        return true;
    }
}
