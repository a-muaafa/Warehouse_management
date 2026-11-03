<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.manage');

        Gate::authorize('users.manage');

        $user = $request->user();

        $query = User::whereIn('role', ['worker', 'storekeeper', 'stock_counter', 'viewer']);

        if (! $user->isSuperAdmin()) {
            $query->where('department_id', $user->department_id);
        }

        $workers = $query->latest()->paginate(15);

        return view('supervisor.workers.index', compact('workers'));
    }

    public function create(): View
    {
        Gate::authorize('users.manage');

        Gate::authorize('users.manage');

        return view('supervisor.workers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');

        Gate::authorize('users.manage');

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'worker_type' => ['required', 'in:intake,auditor,view_only'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $worker = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'worker',
            'worker_type' => $validated['worker_type'],
            'department_id' => $user->department_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLog::log(
            'إضافة عامل',
            "تم إنشاء حساب عامل جديد ({$worker->worker_type_display_name}): {$worker->name} ({$worker->username})",
            $worker->department_id,
            $worker,
            null,
            ['username' => $worker->username, 'role' => $worker->role, 'worker_type' => $worker->worker_type]
        );

        return redirect()->route('supervisor.workers.index')->with('success', __('تم إنشاء حساب العامل وتعيين صلاحياته بنجاح.'));
    }

    public function edit(Request $request, User $worker): View
    {
        $user = $request->user();
        if (! $user->isAdmin() && $worker->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإدارة عمال الأقسام الأخرى.'));
        }

        return view('supervisor.workers.edit', compact('worker'));
    }

    public function update(Request $request, User $worker): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && $worker->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإدارة عمال الأقسام الأخرى.'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$worker->id],
            'email' => ['nullable', 'email', 'unique:users,email,'.$worker->id],
            'password' => ['nullable', 'string', 'min:6'],
            'worker_type' => ['required', 'in:intake,auditor,view_only'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'worker_type' => $validated['worker_type'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $worker->update($updateData);

        $statusText = $worker->is_active ? 'مفعّل' : 'موقف/معطل';
        ActivityLog::log(
            'تعديل حساب عامل',
            "تم تحديث بيانات العامل ({$worker->name}) - النمط: {$worker->worker_type_display_name} - الحالة: {$statusText}",
            $worker->department_id
        );

        return redirect()->route('supervisor.workers.index')->with('success', __('تم تحديث بيانات ومصادقات العامل بنجاح.'));
    }

    public function destroy(Request $request, User $worker): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && $worker->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإدارة عمال الأقسام الأخرى.'));
        }

        $name = $worker->name;
        $deptId = $worker->department_id;
        $worker->delete();

        ActivityLog::log('حذف حساب عامل', "تم حذف حساب العامل: {$name}", $deptId);

        return redirect()->route('supervisor.workers.index')->with('success', __('تم حذف حساب العامل بنجاح.'));
    }
}
