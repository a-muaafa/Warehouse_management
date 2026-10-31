<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('items.view');
    }

    public function view(User $user, Item $item): bool
    {
        if (! $user->hasPermission('items.view')) {
            return false;
        }

        if ($user->department_id && $item->department_id && $item->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('items.manage');
    }

    public function update(User $user, Item $item): bool
    {
        if (! $user->hasPermission('items.manage')) {
            return false;
        }

        if ($user->department_id && $item->department_id && $item->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('items.manage');
    }
}
