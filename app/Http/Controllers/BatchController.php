<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $batchesQuery = ItemBatch::with(['item.primaryUnit', 'warehouse', 'supplier']);

        // Department isolation for non-admin users
        if (! $user->isAdmin() && $user->department_id) {
            $batchesQuery->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id));
        }

        if ($filter === 'expiring_soon') {
            $batchesQuery->expiringSoon(30);
        } elseif ($filter === 'expired') {
            $batchesQuery->expired();
        } elseif ($filter === 'quarantined') {
            $batchesQuery->where('status', 'quarantined');
        } else {
            $batchesQuery->where('quantity_remaining', '>', 0);
        }

        $batches = $batchesQuery->latest()->paginate(20);

        // Counts scoping by department
        $countsQuery = fn ($q) => ! $user->isAdmin() && $user->department_id
            ? $q->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id))
            : $q;

        $counts = [
            'active' => $countsQuery(ItemBatch::active())->count(),
            'expiring_soon' => $countsQuery(ItemBatch::expiringSoon(30))->count(),
            'expired' => $countsQuery(ItemBatch::expired())->count(),
            'quarantined' => $countsQuery(ItemBatch::where('status', 'quarantined'))->count(),
        ];

        return view('batches.index', compact('batches', 'counts', 'filter'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        if ($user->isViewOnlyWorker() || $user->isAuditorWorker()) {
            abort(403, __('غير مسموح لك بإضافة تشغيلات أو دفعات جديدة.'));
        }

        $itemsQuery = Item::query();
        if (! $user->isAdmin() && $user->department_id) {
            $itemsQuery->where('department_id', $user->department_id);
        }

        $items = $itemsQuery->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('batches.create', compact('items', 'warehouses', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isViewOnlyWorker() || $user->isAuditorWorker()) {
            abort(403, __('غير مسموح لك بإضافة تشغيلات أو دفعات جديدة.'));
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'batch_number' => 'required|string|max:100',
            'purchase_cost' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'quantity_received' => 'required|numeric|min:0.01',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'storage_location' => 'nullable|string|max:100',
        ]);

        $item = Item::findOrFail($validated['item_id']);
        if (! $user->isAdmin() && $user->department_id && $item->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإضافة تشغيلة على صنف تابع لقسم آخر.'));
        }

        $validated['quantity_remaining'] = $validated['quantity_received'];
        $validated['status'] = 'active';

        $batch = ItemBatch::create($validated);

        // Update item total quantity
        $item->quantity += (int) $validated['quantity_received'];
        if ($validated['purchase_cost'] > 0) {
            $item->purchase_price = $validated['purchase_cost'];
        }
        $item->save();

        ActivityLog::log('تسجيل تشغيلة/دفعة جديدة', "تم تسجيل التشغيلة رقم ({$batch->batch_number}) للصنف: {$item->name}", $item->department_id);

        return redirect()->route('batches.index')->with('success', __('تم تسجيل التشغيلة/الدفعة الدوائية بنجاح.'));
    }

    public function toggleQuarantine(Request $request, ItemBatch $batch): RedirectResponse
    {
        $user = $request->user();
        if ($user->isViewOnlyWorker()) {
            abort(403, __('غير مسموح لك بتعديل حالة الحجر الصحي.'));
        }

        if (! $user->isAdmin() && $user->department_id && $batch->item?->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بتغيير حالة تشغيلات أدوية/أغذية أقسام أخرى.'));
        }

        $newStatus = $batch->status === 'quarantined' ? 'active' : 'quarantined';
        $batch->update(['status' => $newStatus]);

        $actionText = $newStatus === 'quarantined' ? 'وضع في الحجر الصحي' : 'فك الحجر الصحي والتفعيل';
        ActivityLog::log('تعديل حالة تشغيلة', "تم {$actionText} للدفعة: {$batch->batch_number}", $batch->item?->department_id);

        $msg = $newStatus === 'quarantined'
            ? __('تم نقل التشغيلة :batch إلى الحجر الصحي والتجميع التحفظي.', ['batch' => $batch->batch_number])
            : __('تم إعادة التشغيلة :batch للمخزون النشط والمتاح للصرف.', ['batch' => $batch->batch_number]);

        return back()->with('success', $msg);
    }
}
