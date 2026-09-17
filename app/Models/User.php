<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'worker_type', 'is_active', 'department_id', 'warehouse_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isWarehouseManager(): bool
    {
        return in_array($this->role, ['admin', 'warehouse_manager']);
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, ['admin', 'supervisor']);
    }

    public function isWorker(): bool
    {
        return $this->role === 'worker';
    }

    public function isIntakeWorker(): bool
    {
        return $this->isWorker() && $this->worker_type === 'intake';
    }

    public function isAuditorWorker(): bool
    {
        return $this->isWorker() && $this->worker_type === 'auditor';
    }

    public function isViewOnlyWorker(): bool
    {
        return $this->isWorker() && $this->worker_type === 'view_only';
    }

    public function canCreateTransactions(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isAdmin() || $this->isSupervisor() || $this->isIntakeWorker();
    }

    public function canEditTransactions(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Intake workers cannot edit or delete after saving
        return $this->isAdmin() || $this->isSupervisor();
    }

    public function canPerformAudit(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isAdmin() || $this->isSupervisor() || $this->isAuditorWorker();
    }

    public function canManageWorkers(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isAdmin() || $this->isSupervisor();
    }

    /**
     * Role display name in localized Arabic formatting
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return match ($this->role) {
            'admin' => __('المدير العام (أدمن)'),
            'supervisor' => __('رئيس القسم'),
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
