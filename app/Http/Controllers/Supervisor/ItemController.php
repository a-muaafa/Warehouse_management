<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Item::class);

        $user = $request->user();

        $query = Item::with(['category', 'primaryUnit', 'department']);

        if (! $user->isSuperAdmin() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        $items = $query
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('barcode', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%');
            }))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('alert') && $request->alert === '1', fn ($q) => $q->whereColumn('quantity', '<=', 'min_alert_level'))
            ->orderBy('name')
            ->paginate(20);

        $categories = ItemCategory::all();

        return view('supervisor.items.index', compact('items', 'categories'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Item::class);

        $user = $request->user();
        $categories = ItemCategory::all();
        $units = Unit::where('is_active', true)->get();

        $departments = $user->isSuperAdmin()
            ? Department::all()
            : Department::where('id', $user->department_id)->get();

        return view('supervisor.items.create', compact('categories', 'units', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Item::class);

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:items,barcode'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:items,sku'],
            'unit' => ['nullable', 'string', 'max:50'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'secondary_unit_id' => ['nullable', 'exists:units,id'],
            'category_id' => ['nullable', 'exists:item_categories,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'valuation_method' => ['required', 'in:fifo,lifo,fefo,avg,weighted_average'],
            'min_alert_level' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'storage_cost_rate' => ['nullable', 'numeric', 'min:0'],
            'is_perishable' => ['boolean'],
            'default_location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->isSuperAdmin() && $user->department_id) {
            $validated['department_id'] = $user->department_id;
        } elseif (empty($validated['department_id'])) {
            $validated['department_id'] = Department::first()?->id;
        }

        if (empty($validated['barcode'])) {
            $validated['barcode'] = 'WMS-'.strtoupper(Str::random(8));
        }

        if (empty($validated['sku'])) {
            $validated['sku'] = 'SKU-'.strtoupper(Str::random(6));
        }

        if (! empty($validated['unit_id'])) {
            $u = Unit::find($validated['unit_id']);
            $validated['unit'] = $u ? $u->name : ($validated['unit'] ?? 'قطعة');
        } else {
            $validated['unit'] = $validated['unit'] ?? 'قطعة';
        }

        $validated['is_perishable'] = $request->boolean('is_perishable', false);

        $item = Item::create($validated);

        Cache::forget('dashboard_kpis');

        ActivityLog::log(
            'إضافة صنف',
            "تم إنشاء صنف جديد: {$item->name} (الباركود: {$item->barcode})",
            $item->department_id,
            $item,
            null,
            $item->only(['name', 'barcode', 'sku', 'quantity', 'purchase_price', 'selling_price', 'valuation_method'])
        );

        return redirect()->route('supervisor.items.index')->with('success', __('تم إنشاء الصنف وإضافته لمخزون القسم بنجاح.'));
    }

    public function edit(Request $request, Item $item): View
    {
        Gate::authorize('update', $item);

        $user = $request->user();
        $categories = ItemCategory::all();
        $units = Unit::where('is_active', true)->get();
        $departments = $user->isSuperAdmin()
            ? Department::all()
            : Department::where('id', $user->department_id)->get();

        return view('supervisor.items.edit', compact('item', 'categories', 'units', 'departments'));
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['required', 'string', 'max:100', 'unique:items,barcode,'.$item->id],
            'sku' => ['nullable', 'string', 'max:100', 'unique:items,sku,'.$item->id],
            'unit' => ['nullable', 'string', 'max:50'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'secondary_unit_id' => ['nullable', 'exists:units,id'],
            'category_id' => ['nullable', 'exists:item_categories,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'valuation_method' => ['required', 'in:fifo,lifo,fefo,avg,weighted_average'],
            'min_alert_level' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'storage_cost_rate' => ['nullable', 'numeric', 'min:0'],
            'is_perishable' => ['boolean'],
            'default_location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $user->isSuperAdmin() && $user->department_id) {
            $validated['department_id'] = $user->department_id;
        }

        if (! empty($validated['unit_id'])) {
            $u = Unit::find($validated['unit_id']);
            $validated['unit'] = $u ? $u->name : ($validated['unit'] ?? $item->unit);
        }

        $validated['is_perishable'] = $request->boolean('is_perishable', false);

        $oldValues = $item->only(['name', 'barcode', 'sku', 'purchase_price', 'selling_price', 'valuation_method', 'min_alert_level']);
        $item->update($validated);
        $newValues = $item->only(['name', 'barcode', 'sku', 'purchase_price', 'selling_price', 'valuation_method', 'min_alert_level']);

        Cache::forget('dashboard_kpis');

        ActivityLog::log(
            'تعديل صنف',
            "تم تحديث بيانات الصنف: {$item->name}",
            $item->department_id,
            $item,
            $oldValues,
            $newValues
        );

        return redirect()->route('supervisor.items.index')->with('success', __('تم تحديث بيانات الصنف بنجاح.'));
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        if ($item->transactions()->exists() || $item->batches()->where('quantity_remaining', '>', 0)->exists()) {
            return back()->with('error', __('لا يمكن حذف صنف به حركات مسجلة أو أرصدة متبقية بالتشغيلات.'));
        }

        $name = $item->name;
        $deptId = $item->department_id;
        $oldValues = $item->only(['id', 'name', 'barcode', 'sku']);
        $item->delete();

        Cache::forget('dashboard_kpis');

        ActivityLog::log('حذف صنف', "تم حذف الصنف: {$name}", $deptId, null, $oldValues, null);

        return redirect()->route('supervisor.items.index')->with('success', __('تم حذف الصنف بنجاح.'));
    }
}
