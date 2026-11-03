<?php

namespace App\Providers;

use App\Models\InventoryAudit;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Policies\InventoryAuditPolicy;
use App\Policies\ItemPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WarehouseTransferPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Paginator::defaultView('vendor.pagination.custom');

        // Explicit Policy Bindings
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(WarehouseTransfer::class, WarehouseTransferPolicy::class);
        Gate::policy(InventoryAudit::class, InventoryAuditPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);

        // Super Admin bypasses all checks via Gate::before
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        // Register dynamic gates for permissions defined in config/permissions.php
        $rolesConfig = config('permissions.roles', []);
        $allPermissions = [];
        foreach ($rolesConfig as $permissions) {
            foreach ($permissions as $permission) {
                if ($permission !== '*') {
                    $allPermissions[$permission] = true;
                }
            }
        }

        foreach (array_keys($allPermissions) as $permission) {
            Gate::define($permission, function (User $user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }
    }
}
