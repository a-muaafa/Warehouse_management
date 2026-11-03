<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\TransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseTransferController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WarehouseTransfer::class);

        $user = $request->user();
        $query = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'requester', 'approver', 'items.item']);

        if (! $user->isSuperAdmin()) {
            $accessibleIds = $user->accessibleWarehouseIds();
            $query->where(function ($q) use ($accessibleIds, $user) {
                $q->whereIn('from_warehouse_id', $accessibleIds)
                    ->orWhereIn('to_warehouse_id', $accessibleIds)
                    ->orWhere('requested_by', $user->id);
            });
        }

        $transfers = $query->latest()->paginate(15);

        return view('transfers.index', compact('transfers'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', WarehouseTransfer::class);

        $user = $request->user();
        $warehousesQuery = Warehouse::where('is_active', true);

        if (! $user->isSuperAdmin()) {
            $warehousesQuery->whereIn('id', $user->accessibleWarehouseIds());
        }

        $sourceWarehouses = $warehousesQuery->get();
        $allWarehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('quantity', '>', 0)->with('primaryUnit')->get();

        return view('transfers.create', [
            'warehouses' => $allWarehouses,
            'sourceWarehouses' => $sourceWarehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WarehouseTransfer::class);

        $user = $request->user();

        // Server-side warehouse authorization check BEFORE full validation so unauthorized warehouse attempts immediately return 403
        if ($request->filled('from_warehouse_id') && ! $user->canAccessWarehouse((int) $request->input('from_warehouse_id'))) {
            abort(403, __('غير مصرح لك بإنشاء تحويل من مستودع لا يقع ضمن صلاحياتك.'));
        }

        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'status' => ['nullable', 'in:draft,pending'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        if (! $user->canAccessWarehouse((int) $validated['from_warehouse_id'])) {
            abort(403, __('غير مصرح لك بإنشاء تحويل من مستودع لا يقع ضمن صلاحياتك.'));
        }

        if ($validated['from_warehouse_id'] === $validated['to_warehouse_id']) {
            return back()->withErrors(['to_warehouse_id' => __('مستودع الوجهة يجب أن يختلف عن مستودع المصدر.')]);
        }

        // Prevent duplicate item entries in the same transfer request
        $itemIds = array_column($validated['items'], 'item_id');
        if (count($itemIds) !== count(array_unique($itemIds))) {
            return back()->withErrors(['items' => __('لا يمكن تكرار الصنف نفسه في طلب تحويل واحد.')]);
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $request) {
                $fromWarehouseId = (int) $validated['from_warehouse_id'];

                foreach ($validated['items'] as $itemData) {
                    $item = Item::findOrFail($itemData['item_id']);
                    $requestedQty = (float) $itemData['quantity'];

                    $availableInSource = (float) ItemBatch::where('item_id', $item->id)
                        ->where('warehouse_id', $fromWarehouseId)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->where(function ($q) {
                            $q->whereNull('expiry_date')
                                ->orWhereDate('expiry_date', '>=', today());
                        })
                        ->sum('quantity_remaining');

                    if ($availableInSource < $requestedQty) {
                        throw new \DomainException(
                            __('الكمية المطلوبة للصنف ":name" غير متوفرة في مستودع المصدر. المتوفر: :available', [
                                'name' => $item->name,
                                'available' => $availableInSource,
                            ])
                        );
                    }
                }

                $transfer = WarehouseTransfer::create([
                    'transfer_number' => 'TRF-'.strtoupper(uniqid()),
                    'from_warehouse_id' => $validated['from_warehouse_id'],
                    'to_warehouse_id' => $validated['to_warehouse_id'],
                    'status' => $validated['status'] ?? 'pending',
                    'requested_by' => $request->user()->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $itemData) {
                    $item = Item::findOrFail($itemData['item_id']);
                    TransferItem::create([
                        'warehouse_transfer_id' => $transfer->id,
                        'item_id' => $item->id,
                        'quantity' => (float) $itemData['quantity'],
                        'received_quantity' => 0,
                        'missing_quantity' => 0,
                        'unit_cost' => (float) ($item->purchase_price ?: 0),
                    ]);
                }

                return $transfer;
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        ActivityLog::log(
            'إنشاء طلب تحويل',
            "تم إنشاء طلب تحويل رقم: {$transfer->transfer_number}",
            null,
            $transfer,
            null,
            ['from_warehouse_id' => $transfer->from_warehouse_id, 'to_warehouse_id' => $transfer->to_warehouse_id, 'status' => $transfer->status]
        );

        return redirect()->route('transfers.index')
            ->with('success', __('تم إنشاء طلب التحويل الداخلي بنجاح.'));
    }

    public function show(WarehouseTransfer $transfer): View
    {
        Gate::authorize('view', $transfer);

        $transfer->load(['fromWarehouse', 'toWarehouse', 'requester', 'approver', 'dispatcher', 'receiver', 'items.item', 'items.batch']);

        return view('transfers.show', compact('transfer'));
    }

    /**
     * Approve a draft or pending transfer before dispatch.
     */
    public function approveTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('approve', $transfer);

        if (! in_array($transfer->status, ['draft', 'pending'], true)) {
            return back()->with('error', __('لا يمكن اعتماد التحويل في حالته الحالية.'));
        }

        $oldStatus = $transfer->status;
        $transfer->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::log(
            'اعتماد تحويل',
            "تم اعتماد التحويل رقم: {$transfer->transfer_number}",
            null,
            $transfer,
            ['status' => $oldStatus],
            ['status' => 'approved']
        );

        return back()->with('success', __('تم اعتماد طلب التحويل بنجاح، وهو جاهز للإرسال.'));
    }

    /**
     * Dispatch items from source warehouse.
     * Deducts quantities from source batches, records outbound transactions, batch links, and ledger entries.
     */
    public function dispatchTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('dispatch', $transfer);

        if (! in_array($transfer->status, ['draft', 'pending', 'approved'], true)) {
            return back()->with('error', __('لا يمكن إرسال التحويل في حالته الحالية.'));
        }

        try {
            DB::transaction(function () use ($request, $transfer) {
                $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, ['draft', 'pending', 'approved'], true)) {
                    throw new \DomainException(__('تم إرسال هذا التحويل مسبقاً.'));
                }

                $locked->load('items.item');

                foreach ($locked->items as $transferItem) {
                    $item = Item::where('id', $transferItem->item_id)->lockForUpdate()->firstOrFail();
                    $qtyBefore = (float) $item->quantity;

                    $allocations = $this->valuationService->allocateOutboundStock(
                        $item,
                        (float) $transferItem->quantity,
                        $locked->from_warehouse_id
                    );

                    $item->decrement('quantity', (float) $transferItem->quantity);

                    $totalCost = collect($allocations)->sum('total_cost');
                    $avgUnitCost = (float) $transferItem->quantity > 0
                        ? round($totalCost / (float) $transferItem->quantity, 4)
                        : (float) $transferItem->unit_cost;

                    // Persist actual batch unit cost and primary batch ID on TransferItem so receipt preserves cost
                    $primaryBatchId = count($allocations) === 1 ? $allocations[0]['batch_id'] : ($allocations[0]['batch_id'] ?? null);
                    $transferItem->update([
                        'unit_cost' => $avgUnitCost,
                        'item_batch_id' => $primaryBatchId,
                    ]);

                    $tx = Transaction::create([
                        'item_id' => $item->id,
                        'user_id' => $request->user()->id,
                        'warehouse_id' => $locked->from_warehouse_id,
                        'item_batch_id' => count($allocations) === 1 ? $allocations[0]['batch_id'] : null,
                        'type' => 'out',
                        'movement_reason' => 'transfer_out',
                        'quantity' => (float) $transferItem->quantity,
                        'unit_price' => $avgUnitCost,
                        'total_amount' => round($totalCost, 2),
                        'reference_doc' => $locked->transfer_number,
                        'notes' => 'تحويل داخلي إلى: '.($locked->toWarehouse->name ?? '—'),
                        'status' => 'completed',
                    ]);

                    $runningBefore = $qtyBefore;
                    foreach ($allocations as $alloc) {
                        TransactionBatchItem::create([
                            'transaction_id' => $tx->id,
                            'item_batch_id' => $alloc['batch_id'],
                            'quantity' => $alloc['quantity'],
                            'unit_cost' => $alloc['unit_cost'],
                            'total_cost' => $alloc['total_cost'],
                        ]);

                        $runningAfter = round($runningBefore - (float) $alloc['quantity'], 4);
                        InventoryMovement::record([
                            'reference_number' => $locked->transfer_number,
                            'movement_type' => 'transfer_out',
                            'source_type' => WarehouseTransfer::class,
                            'source_id' => $locked->id,
                            'item_id' => $item->id,
                            'warehouse_id' => $locked->from_warehouse_id,
                            'item_batch_id' => $alloc['batch_id'],
                            'quantity_before' => $runningBefore,
                            'quantity_change' => -(float) $alloc['quantity'],
                            'quantity_after' => $runningAfter,
                            'unit_cost' => $alloc['unit_cost'],
                            'total_cost' => $alloc['total_cost'],
                            'performed_by' => $request->user()->id,
                            'notes' => 'Transfer dispatch to warehouse #'.$locked->to_warehouse_id,
                        ]);
                        $runningBefore = $runningAfter;
                    }
                }

                $locked->update([
                    'status' => 'in_transit',
                    'approved_by' => $locked->approved_by ?? $request->user()->id,
                    'approved_at' => $locked->approved_at ?? now(),
                    'dispatched_by' => $request->user()->id,
                    'dispatched_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        Cache::forget('dashboard_kpis');

        ActivityLog::log(
            'إرسال تحويل',
            "تم إرسال التحويل رقم: {$transfer->transfer_number}",
            null,
            $transfer,
            ['status' => $transfer->status],
            ['status' => 'in_transit']
        );

        return back()->with('success', __('تم تغيير حالة التحويل إلى "في الطريق" وخصم الكمية من المصدر.'));
    }

    /**
     * Receive items at destination warehouse.
     * Supports full or partial receipt, records missing_quantity and discrepancy_reason,
     * and sets status to 'completed' (full receipt) or 'partially_received' (when missing > 0).
     */
    public function receiveTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('receive', $transfer);

        if ($transfer->status !== 'in_transit') {
            return back()->with('error', __('فقط التحويلات قيد النقل يمكن استلامها.'));
        }

        $request->validate([
            'received_quantities' => ['nullable', 'array'],
            'received_quantities.*' => ['nullable', 'numeric', 'min:0'],
            'discrepancy_reasons' => ['nullable', 'array'],
            'discrepancy_reasons.*' => ['nullable', 'string', 'max:500'],
            'discrepancy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($request, $transfer) {
                $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'in_transit') {
                    throw new \DomainException(__('تم استلام هذا التحويل مسبقاً.'));
                }

                $locked->load(['items.item', 'items.batch']);
                $receivedQtys = $request->input('received_quantities', []);
                $reasons = $request->input('discrepancy_reasons', []);
                $hasDiscrepancy = false;

                foreach ($locked->items as $transferItem) {
                    $dispatchedQty = (float) $transferItem->quantity;
                    $receivedQty = isset($receivedQtys[$transferItem->id])
                        ? (float) $receivedQtys[$transferItem->id]
                        : $dispatchedQty;

                    if ($receivedQty > $dispatchedQty) {
                        throw new \DomainException(__('الكمية المستلمة لا يمكن أن تتجاوز الكمية المرسلة.'));
                    }

                    $missingQty = round($dispatchedQty - $receivedQty, 4);
                    if ($missingQty > 0) {
                        $hasDiscrepancy = true;
                    }

                    $reason = $reasons[$transferItem->id] ?? ($missingQty > 0 ? ($request->input('discrepancy_notes') ?? 'فرق استلام قيد المراجعة') : null);

                    $transferItem->update([
                        'received_quantity' => $receivedQty,
                        'missing_quantity' => $missingQty,
                        'discrepancy_reason' => $reason,
                    ]);

                    if ($receivedQty <= 0) {
                        continue;
                    }

                    $item = Item::where('id', $transferItem->item_id)->lockForUpdate()->firstOrFail();
                    $qtyBefore = (float) $item->quantity;

                    // Preserve manufacture/expiry dates from source batch if available
                    $sourceBatch = $transferItem->batch;

                    $destBatch = ItemBatch::create([
                        'item_id' => $transferItem->item_id,
                        'warehouse_id' => $locked->to_warehouse_id,
                        'batch_number' => 'TRF-'.$locked->transfer_number.'-'.strtoupper(substr(uniqid(), -4)),
                        'purchase_cost' => (float) $transferItem->unit_cost,
                        'quantity_received' => $receivedQty,
                        'quantity_remaining' => $receivedQty,
                        'manufacture_date' => $sourceBatch?->manufacture_date,
                        'expiry_date' => $sourceBatch?->expiry_date,
                        'received_at' => now(),
                        'status' => 'active',
                    ]);

                    $item->increment('quantity', $receivedQty);

                    $tx = Transaction::create([
                        'item_id' => $transferItem->item_id,
                        'user_id' => $request->user()->id,
                        'warehouse_id' => $locked->to_warehouse_id,
                        'item_batch_id' => $destBatch->id,
                        'type' => 'in',
                        'movement_reason' => 'transfer_in',
                        'quantity' => $receivedQty,
                        'unit_price' => (float) $transferItem->unit_cost,
                        'total_amount' => round($receivedQty * (float) $transferItem->unit_cost, 2),
                        'reference_doc' => $locked->transfer_number,
                        'notes' => 'استلام تحويل داخلي من: '.($locked->fromWarehouse->name ?? '—').($missingQty > 0 ? " (عجز/مفقود: {$missingQty})" : ''),
                        'status' => 'completed',
                    ]);

                    $destBatch->update(['transaction_id' => $tx->id]);

                    TransactionBatchItem::create([
                        'transaction_id' => $tx->id,
                        'item_batch_id' => $destBatch->id,
                        'quantity' => $receivedQty,
                        'unit_cost' => (float) $transferItem->unit_cost,
                        'total_cost' => round($receivedQty * (float) $transferItem->unit_cost, 4),
                    ]);

                    InventoryMovement::record([
                        'reference_number' => $locked->transfer_number,
                        'movement_type' => 'transfer_in',
                        'source_type' => WarehouseTransfer::class,
                        'source_id' => $locked->id,
                        'item_id' => $item->id,
                        'warehouse_id' => $locked->to_warehouse_id,
                        'item_batch_id' => $destBatch->id,
                        'quantity_before' => $qtyBefore,
                        'quantity_change' => $receivedQty,
                        'quantity_after' => round($qtyBefore + $receivedQty, 4),
                        'unit_cost' => (float) $transferItem->unit_cost,
                        'total_cost' => round($receivedQty * (float) $transferItem->unit_cost, 4),
                        'performed_by' => $request->user()->id,
                        'notes' => 'Transfer receipt from warehouse #'.$locked->from_warehouse_id,
                    ]);
                }

                // Do not mark as normally completed if there is an unresolved discrepancy
                $newStatus = $hasDiscrepancy ? 'partially_received' : 'completed';

                $locked->update([
                    'status' => $newStatus,
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                    'discrepancy_notes' => $request->input('discrepancy_notes') ?? $locked->discrepancy_notes,
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        Cache::forget('dashboard_kpis');

        $transfer->refresh();
        ActivityLog::log(
            'استلام تحويل',
            "تم استلام التحويل رقم: {$transfer->transfer_number} بحالة: {$transfer->status}",
            null,
            $transfer,
            ['status' => 'in_transit'],
            ['status' => $transfer->status]
        );

        return back()->with('success', __('تم استلام التحويل وتحديث أرصدة المستودع الوجهة بنجاح.'));
    }

    /**
     * Resolve transfer discrepancy (for partially_received / discrepancy transfers).
     */
    public function resolveDiscrepancy(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('resolve', $transfer);

        if (! in_array($transfer->status, ['partially_received', 'discrepancy'], true)) {
            return back()->with('error', __('لا توجد فروقات معلقة لهذا التحويل.'));
        }

        $validated = $request->validate([
            'resolution' => ['required', 'in:write_off,return_to_source,mark_discrepancy'],
            'discrepancy_notes' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $transfer, $validated) {
            $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();
            $locked->load(['items.item', 'items.batch']);

            if ($validated['resolution'] === 'return_to_source') {
                // Restore missing quantities back to the source warehouse
                foreach ($locked->items as $transferItem) {
                    $missingQty = (float) $transferItem->missing_quantity;
                    if ($missingQty <= 0) {
                        continue;
                    }

                    $item = Item::where('id', $transferItem->item_id)->lockForUpdate()->firstOrFail();
                    $qtyBefore = (float) $item->quantity;

                    $restoredBatch = ItemBatch::create([
                        'item_id' => $item->id,
                        'warehouse_id' => $locked->from_warehouse_id,
                        'batch_number' => 'RET-'.$locked->transfer_number.'-'.strtoupper(substr(uniqid(), -4)),
                        'purchase_cost' => (float) $transferItem->unit_cost,
                        'quantity_received' => $missingQty,
                        'quantity_remaining' => $missingQty,
                        'manufacture_date' => $transferItem->batch?->manufacture_date,
                        'expiry_date' => $transferItem->batch?->expiry_date,
                        'received_at' => now(),
                        'status' => 'active',
                    ]);

                    $item->increment('quantity', $missingQty);

                    InventoryMovement::record([
                        'reference_number' => $locked->transfer_number,
                        'movement_type' => 'transfer_discrepancy_return',
                        'source_type' => WarehouseTransfer::class,
                        'source_id' => $locked->id,
                        'item_id' => $item->id,
                        'warehouse_id' => $locked->from_warehouse_id,
                        'item_batch_id' => $restoredBatch->id,
                        'quantity_before' => $qtyBefore,
                        'quantity_change' => $missingQty,
                        'quantity_after' => round($qtyBefore + $missingQty, 4),
                        'unit_cost' => (float) $transferItem->unit_cost,
                        'total_cost' => round($missingQty * (float) $transferItem->unit_cost, 4),
                        'performed_by' => $request->user()->id,
                        'notes' => $validated['discrepancy_notes'],
                    ]);
                }
            }

            $finalStatus = $validated['resolution'] === 'mark_discrepancy' ? 'discrepancy' : 'completed';

            $locked->update([
                'status' => $finalStatus,
                'discrepancy_notes' => $validated['discrepancy_notes'],
            ]);
        });

        ActivityLog::log(
            'تسوية فروقات تحويل',
            "تم تسوية فروقات التحويل رقم: {$transfer->transfer_number}",
            null,
            $transfer
        );

        return back()->with('success', __('تم توثيق وتسوية فروقات التحويل بنجاح.'));
    }
}
