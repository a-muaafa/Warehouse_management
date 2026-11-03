<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('batches.view');

        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $batchesQuery = ItemBatch::with(['item.primaryUnit', 'warehouse', 'supplier']);

        if (! $user->isSuperAdmin()) {
            $accessibleIds = $user->accessibleWarehouseIds();
            if (! empty($accessibleIds)) {
                $batchesQuery->where(function ($q) use ($accessibleIds) {
                    $q->whereIn('warehouse_id', $accessibleIds)
                        ->orWhereNull('warehouse_id');
                });
            }

            if ($user->department_id) {
                $batchesQuery->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id));
            }
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

        $countsQuery = function ($q) use ($user) {
            if (! $user->isSuperAdmin()) {
                $accessibleIds = $user->accessibleWarehouseIds();
                if (! empty($accessibleIds)) {
                    $q->where(function ($sub) use ($accessibleIds) {
                        $sub->whereIn('warehouse_id', $accessibleIds)
                            ->orWhereNull('warehouse_id');
                    });
                }
                if ($user->department_id) {
                    $q->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id));
                }
            }

            return $q;
        };

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
        if (! $user->hasPermission('stock.receive')) {
            abort(403, __('غير مسموح لك بإضافة تشغيلات أو دفعات جديدة.'));
        }

        $itemsQuery = Item::query();
        if (! $user->isSuperAdmin() && $user->department_id) {
            $itemsQuery->where('department_id', $user->department_id);
        }

        $warehousesQuery = Warehouse::where('is_active', true);
        if (! $user->isSuperAdmin()) {
            $warehousesQuery->whereIn('id', $user->accessibleWarehouseIds());
        }

        $items = $itemsQuery->orderBy('name')->get();
        $warehouses = $warehousesQuery->get();
        $suppliers = Supplier::where('is_active', true)->get();

        return view('batches.create', compact('items', 'warehouses', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('stock.receive')) {
            abort(403, __('غير مسموح لك بإضافة تشغيلات أو دفعات جديدة.'));
        }

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'batch_number' => ['required', 'string', 'max:100'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'quantity_received' => ['required', 'numeric', 'min:0.0001'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'storage_location' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($validated['warehouse_id']) && ! $user->canAccessWarehouse((int) $validated['warehouse_id'])) {
            abort(403, __('غير مصرح لك بإضافة دفعة في مستودع خارج نطاق صلاحياتك.'));
        }

        $item = Item::findOrFail($validated['item_id']);
        if (! $user->isSuperAdmin() && $user->department_id && $item->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإضافة تشغيلة على صنف تابع لقسم آخر.'));
        }

        $qty = (float) $validated['quantity_received'];
        $cost = (float) $validated['purchase_cost'];

        $batch = DB::transaction(function () use ($validated, $item, $qty, $cost, $user) {
            $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();
            $qtyBefore = (float) $lockedItem->quantity;

            $validated['quantity_remaining'] = $qty;
            $validated['received_at'] = now();
            $validated['status'] = 'active';

            $batch = ItemBatch::create($validated);

            $lockedItem->increment('quantity', $qty);
            if ($cost > 0) {
                $lockedItem->purchase_price = $cost;
                $lockedItem->save();
            }

            $tx = Transaction::create([
                'item_id' => $lockedItem->id,
                'user_id' => $user->id,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'item_batch_id' => $batch->id,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'unit_id' => $lockedItem->unit_id,
                'type' => 'in',
                'movement_reason' => 'purchase',
                'quantity' => $qty,
                'unit_price' => $cost,
                'total_amount' => round($qty * $cost, 2),
                'reference_doc' => $batch->batch_number,
                'notes' => 'إدخال دفعة مباشرة رقم: '.$batch->batch_number,
                'status' => 'completed',
            ]);

            $batch->update(['transaction_id' => $tx->id]);

            TransactionBatchItem::create([
                'transaction_id' => $tx->id,
                'item_batch_id' => $batch->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'total_cost' => round($qty * $cost, 4),
            ]);

            InventoryMovement::record([
                'reference_number' => $batch->batch_number,
                'movement_type' => 'receipt',
                'source_type' => Transaction::class,
                'source_id' => $tx->id,
                'item_id' => $lockedItem->id,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'item_batch_id' => $batch->id,
                'quantity_before' => $qtyBefore,
                'quantity_change' => $qty,
                'quantity_after' => round($qtyBefore + $qty, 4),
                'unit_cost' => $cost,
                'total_cost' => round($qty * $cost, 4),
                'performed_by' => $user->id,
                'notes' => 'Batch creation receipt',
            ]);

            return $batch;
        });

        Cache::forget('dashboard_kpis');

        ActivityLog::log(
            'تسجيل تشغيلة/دفعة جديدة',
            "تم تسجيل التشغيلة رقم ({$batch->batch_number}) للصنف: {$item->name}",
            $item->department_id,
            $batch,
            null,
            ['batch_number' => $batch->batch_number, 'quantity_received' => $qty]
        );

        return redirect()->route('batches.index')->with('success', __('تم تسجيل التشغيلة/الدفعة الدوائية بنجاح.'));
    }

    public function toggleQuarantine(Request $request, ItemBatch $batch): RedirectResponse
    {
        Gate::authorize('batches.quarantine');

        $user = $request->user();
        if ($batch->warehouse_id && ! $user->canAccessWarehouse($batch->warehouse_id)) {
            abort(403, __('غير مصرح لك بتعديل دفعة في مستودع خارج نطاق صلاحياتك.'));
        }

        if (! $user->isSuperAdmin() && $user->department_id && $batch->item?->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بتغيير حالة تشغيلات أدوية/أغذية أقسام أخرى.'));
        }

        $oldStatus = $batch->status;
        $newStatus = $batch->status === 'quarantined' ? 'active' : 'quarantined';
        $batch->update(['status' => $newStatus]);

        $actionText = $newStatus === 'quarantined' ? 'وضع في الحجر الصحي' : 'فك الحجر الصحي والتفعيل';
        ActivityLog::log(
            'تعديل حالة تشغيلة',
            "تم {$actionText} للدفعة: {$batch->batch_number}",
            $batch->item?->department_id,
            $batch,
            ['status' => $oldStatus],
            ['status' => $newStatus]
        );

        $msg = $newStatus === 'quarantined'
            ? __('تم نقل التشغيلة :batch إلى الحجر الصحي والتجميع التحفظي.', ['batch' => $batch->batch_number])
            : __('تم إعادة التشغيلة :batch للمخزون النشط والمتاح للصرف.', ['batch' => $batch->batch_number]);

        return back()->with('success', $msg);
    }
}
