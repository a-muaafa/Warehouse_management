<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'worker_type', 'is_active', 'department_id', 'warehouse_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_user')->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transfersRequested(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'requested_by');
    }

    public function auditsConducted(): HasMany
    {
        return $this->hasMany(InventoryAudit::class, 'audited_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Check if user can access a given warehouse.
     * super_admin has global access to all warehouses.
     */
    public function canAccessWarehouse(int|Warehouse|null $warehouse): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($warehouse === null) {
            return false;
        }

        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : (int) $warehouse;

        return $this->warehouses()->where('warehouses.id', $warehouseId)->exists()
            || (int) $this->warehouse_id === $warehouseId;
    }

    /**
     * Return list of accessible warehouse IDs.
     */
    public function accessibleWarehouseIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Warehouse::pluck('id')->all();
        }

        $ids = $this->warehouses()->pluck('warehouses.id')->all();
        if ($this->warehouse_id && ! in_array($this->warehouse_id, $ids, true)) {
            $ids[] = (int) $this->warehouse_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Assign a role to the user and persist it.
     */
    public function assignRole(string $role): self
    {
        $this->update(['role' => $role]);

        return $this;
    }

    /**
     * Resolve effective role in the 7-role RBAC matrix (maps legacy roles).
     */
    public function effectiveRole(): string
    {
        return match ($this->role) {
            'admin', 'super_admin' => 'super_admin',
            'supervisor', 'inventory_manager' => 'inventory_manager',
            'worker' => match ($this->worker_type) {
                'auditor' => 'stock_counter',
                'view_only' => 'viewer',
                default => 'storekeeper',
            },
            default => $this->role,
        };
    }

    /**
     * Check if user has a permission string based on role matrix.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $effectiveRole = $this->effectiveRole();
        $permissions = config("permissions.roles.{$effectiveRole}", []);

        return in_array($permission, $permissions, true) || in_array('*', $permissions, true);
    }

    /**
     * Check if user has specific role or roles.
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        if (in_array('super_admin', $roles, true) && $this->isSuperAdmin()) {
            return true;
        }

        return in_array($this->role, $roles, true);
    }

    // Role checks
    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    public function isWarehouseManager(): bool
    {
        return $this->role === 'warehouse_manager';
    }

    public function isInventoryManager(): bool
    {
        return in_array($this->role, ['inventory_manager', 'supervisor'], true);
    }

    public function isStorekeeper(): bool
    {
        return $this->role === 'storekeeper';
    }

    public function isStockCounter(): bool
    {
        return $this->role === 'stock_counter';
    }

    public function isAuditor(): bool
    {
        return in_array($this->role, ['auditor', 'stock_counter'], true);
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    // Backwards compatibility methods
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'warehouse_manager', 'inventory_manager', 'supervisor'], true);
    }

    public function isWorker(): bool
    {
        return in_array($this->role, ['storekeeper', 'stock_counter', 'viewer', 'worker'], true);
    }

    public function isIntakeWorker(): bool
    {
        return $this->role === 'storekeeper' || ($this->role === 'worker' && $this->worker_type === 'intake');
    }

    public function isAuditorWorker(): bool
    {
        return $this->role === 'stock_counter' || ($this->role === 'worker' && $this->worker_type === 'auditor');
    }

    public function isViewOnlyWorker(): bool
    {
        return $this->role === 'viewer' || ($this->role === 'worker' && $this->worker_type === 'view_only');
    }

    public function canCreateTransactions(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasPermission('stock.receive') || $this->hasPermission('stock.issue');
    }

    public function canEditTransactions(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isSuperAdmin() || $this->isWarehouseManager();
    }

    public function canPerformAudit(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasPermission('audits.create') || $this->hasPermission('audits.count');
    }

    public function canManageWorkers(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasPermission('users.manage');
    }

    /**
     * Role display name in localized Arabic formatting
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return match ($this->role) {
            'super_admin', 'admin' => __('المدير العام (Super Admin)'),
            'warehouse_manager' => __('مدير المستودع (Warehouse Manager)'),
            'inventory_manager', 'supervisor' => __('مدير المخزون (Inventory Manager)'),
            'storekeeper' => __('أمين المستودع (Storekeeper)'),
            'stock_counter' => __('عامل الجرد (Stock Counter)'),
            'auditor' => __('مدقق ومراجع (Auditor)'),
            'viewer' => __('مشاهد (Viewer)'),
            'worker' => __('عامل مستودع'),
            default => ucfirst($this->role),
        };
    }

    /**
     * Worker type display name in localized Arabic formatting
     */
    public function getWorkerTypeDisplayNameAttribute(): string
    {
        return match ($this->worker_type) {
            'intake' => __('عامل إدخال وتوثيق'),
            'auditor' => __('عامل الجرد والفرز'),
            'view_only' => __('عامل مشاهد (عرض فقط)'),
            default => __('غير محدد'),
        };
    }
}
