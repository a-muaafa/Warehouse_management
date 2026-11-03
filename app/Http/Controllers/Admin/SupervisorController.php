<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.manage');

        $supervisors = User::whereIn('role', ['supervisor', 'inventory_manager', 'warehouse_manager', 'auditor'])->with('department')->latest()->paginate(15);

        return view('admin.supervisors.index', compact('supervisors'));
    }

    public function create(): View
    {
        Gate::authorize('users.manage');

        $departments = Department::orderBy('name')->get();

        return view('admin.supervisors.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'in:supervisor,inventory_manager,warehouse_manager,auditor'],
            'department_id' => ['required', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $targetRole = $validated['role'] ?? 'supervisor';

        $supervisor = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $targetRole,
            'department_id' => $validated['department_id'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLog::log(
            'إضافة رئيس قسم',
            "تم إنشاء حساب رئيس القسم: {$supervisor->name} ({$supervisor->username})",
            $supervisor->department_id,
            $supervisor,
            null,
            ['username' => $supervisor->username, 'role' => $supervisor->role, 'department_id' => $supervisor->department_id]
        );

        return redirect()->route('admin.supervisors.index')->with('success', __('تم إنشاء حساب رئيس القسم بنجاح.'));
    }

    public function edit(User $supervisor): View
    {
        $departments = Department::orderBy('name')->get();

        return view('admin.supervisors.edit', compact('supervisor', 'departments'));
    }

    public function update(Request $request, User $supervisor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$supervisor->id],
            'email' => ['nullable', 'email', 'unique:users,email,'.$supervisor->id],
            'password' => ['nullable', 'string', 'min:6'],
            'department_id' => ['required', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'department_id' => $validated['department_id'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $supervisor->update($updateData);

        ActivityLog::log('تعديل رئيس قسم', "تم تعديل بيانات رئيس القسم: {$supervisor->name} ({$supervisor->username})", $supervisor->department_id);

        return redirect()->route('admin.supervisors.index')->with('success', __('تم تحديث بيانات رئيس القسم بنجاح.'));
    }

    public function destroy(User $supervisor): RedirectResponse
    {
        $name = $supervisor->name;
        $deptId = $supervisor->department_id;
        $supervisor->delete();

        ActivityLog::log('حذف رئيس قسم', "تم حذف حساب رئيس القسم: {$name}", $deptId);

        return redirect()->route('admin.supervisors.index')->with('success', __('تم حذف حساب رئيس القسم بنجاح.'));
    }
}
