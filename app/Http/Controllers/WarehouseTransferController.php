<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\TransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $query = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'requester', 'items.item']);

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

        $validated = $request->validate([
            'from_warehouse_id'      => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id'        => ['required', 'integer', 'exists:warehouses,id'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_id'        => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
        ]);

        // Server-side warehouse authorization check
        if (! $user->canAccessWarehouse((int) $validated['from_warehouse_id'])) {
            abort(403, __('غير مصرح لك بإنشاء تحويل من مستودع لا يقع ضمن صلاحياتك.'));
        }

        // Server-side: source ≠ destination
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

                // Validate that each item actually has sufficient stock in the SOURCE warehouse
                foreach ($validated['items'] as $itemData) {
                    $item = Item::findOrFail($itemData['item_id']);
                    $requestedQty = (float) $itemData['quantity'];

                    $availableInSource = (float) ItemBatch::where('item_id', $item->id)
                        ->where('warehouse_id', $fromWarehouseId)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->sum('quantity_remaining');

                    if ($availableInSource < $requestedQty) {
                        throw new \DomainException(
                            __('الكمية المطلوبة للصنف ":name" غير متوفرة في مستودع المصدر. المتوفر: :available', [
                                'name'      => $item->name,
                                'available' => $availableInSource,
                            ])
                        );
                    }
                }

                $transfer = WarehouseTransfer::create([
                    'transfer_number'  => 'TRF-'.strtoupper(uniqid()),
                    'from_warehouse_id'=> $validated['from_warehouse_id'],
                    'to_warehouse_id'  => $validated['to_warehouse_id'],
                    'status'           => 'pending',
                    'requested_by'     => $request->user()->id,
                    'notes'            => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $itemData) {
                    $item = Item::findOrFail($itemData['item_id']);
                    TransferItem::create([
                        'warehouse_transfer_id' => $transfer->id,
                        'item_id'               => $item->id,
                        'quantity'              => (float) $itemData['quantity'],
                        'unit_cost'             => (float) ($item->purchase_price ?: 0),
                    ]);
                }

                return $transfer;
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        ActivityLog::log('إنشاء طلب تحويل', "تم إنشاء طلب تحويل رقم: {$transfer->transfer_number}");

        return redirect()->route('transfers.index')
            ->with('success', __('تم إنشاء طلب التحويل الداخلي بنجاح.'));
    }

    public function show(WarehouseTransfer $transfer): View
    {
        Gate::authorize('view', $transfer);

        $transfer->load(['fromWarehouse', 'toWarehouse', 'requester', 'dispatcher', 'receiver', 'items.item', 'items.batch']);

        return view('transfers.show', compact('transfer'));
    }

    /**
     * Dispatch items from source warehouse.
     * Deducts quantities from source batches and records outbound transactions.
     */
    public function dispatchTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('dispatch', $transfer);

        if ($transfer->status !== 'pending') {
            return back()->with('error', __('لا يمكن إرسال التحويل في حالته الحالية.'));
        }

        try {
            DB::transaction(function () use ($request, $transfer) {
                // Lock the transfer row to prevent concurrent dispatch attempts
                $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'pending') {
                    throw new \DomainException(__('تم إرسال هذا التحويل مسبقاً.'));
                }

                $locked->load('items.item');

                foreach ($locked->items as $transferItem) {
                    $item = Item::where('id', $transferItem->item_id)->lockForUpdate()->firstOrFail();

                    // Allocate from source warehouse batches (throws DomainException if insufficient)
                    $allocations = $this->valuationService->allocateOutboundStock(
                        $item,
                        (float) $transferItem->quantity,
                        $locked->from_warehouse_id
                    );

                    // Decrement global item quantity
                    $item->decrement('quantity', (float) $transferItem->quantity);

                    // Use the actual weighted-average cost from allocated batches
                    $totalCost = collect($allocations)->sum('total_cost');
                    $avgUnitCost = (float) $transferItem->quantity > 0
                        ? $totalCost / (float) $transferItem->quantity
                        : (float) $transferItem->unit_cost;

                    Transaction::create([
                        'item_id'         => $item->id,
                        'user_id'         => $request->user()->id,
                        'warehouse_id'    => $locked->from_warehouse_id,
                        'type'            => 'out',
                        'movement_reason' => 'transfer_out',
                        'quantity'        => (float) $transferItem->quantity,
                        'unit_price'      => round($avgUnitCost, 4),
                        'total_amount'    => round($totalCost, 2),
                        'reference_doc'   => $locked->transfer_number,
                        'notes'           => 'تحويل داخلي إلى: '.($locked->toWarehouse->name ?? '—'),
                        'status'          => 'completed',
                    ]);
                }

                $locked->update([
                    'status'        => 'in_transit',
                    'dispatched_by' => $request->user()->id,
                    'dispatched_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::log('إرسال تحويل', "تم إرسال التحويل رقم: {$transfer->transfer_number}");

        return back()->with('success', __('تم تغيير حالة التحويل إلى "في الطريق" وخصم الكمية من المصدر.'));
    }

    /**
     * Receive items at destination warehouse.
     * Creates new destination batches and records inbound transactions.
     */
    public function receiveTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        Gate::authorize('receive', $transfer);

        if ($transfer->status !== 'in_transit') {
            return back()->with('error', __('فقط التحويلات قيد النقل يمكن استلامها.'));
        }

        // Allow partial receipt: operator can specify actual received quantities
        $request->validate([
            'received_quantities'         => ['nullable', 'array'],
            'received_quantities.*'       => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($request, $transfer) {
                // Lock to prevent double-receive
                $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'in_transit') {
                    throw new \DomainException(__('تم استلام هذا التحويل مسبقاً.'));
                }

                $locked->load('items.item');
                $receivedQtys = $request->input('received_quantities', []);

                foreach ($locked->items as $transferItem) {
                    $receivedQty = isset($receivedQtys[$transferItem->id])
                        ? (float) $receivedQtys[$transferItem->id]
                        : (float) $transferItem->quantity;

                    // Received cannot exceed dispatched
                    $receivedQty = min($receivedQty, (float) $transferItem->quantity);

                    $transferItem->update(['received_quantity' => $receivedQty]);

                    if ($receivedQty <= 0) {
                        continue;
                    }

                    $item = Item::where('id', $transferItem->item_id)->lockForUpdate()->firstOrFail();

                    // Create a destination-warehouse batch for the received quantity
                    ItemBatch::create([
                        'item_id'           => $transferItem->item_id,
                        'warehouse_id'      => $locked->to_warehouse_id,
                        'batch_number'      => 'TRF-'.$locked->transfer_number.'-'.strtoupper(substr(uniqid(), -4)),
                        'purchase_cost'     => (float) $transferItem->unit_cost,
                        'quantity_received' => $receivedQty,
                        'quantity_remaining'=> $receivedQty,
                        'status'            => 'active',
                    ]);

                    // Increment global item quantity by actually received amount
                    $item->increment('quantity', $receivedQty);

                    Transaction::create([
                        'item_id'         => $transferItem->item_id,
                        'user_id'         => $request->user()->id,
                        'warehouse_id'    => $locked->to_warehouse_id,
                        'type'            => 'in',
                        'movement_reason' => 'transfer_in',
                        'quantity'        => $receivedQty,
                        'unit_price'      => (float) $transferItem->unit_cost,
                        'total_amount'    => round($receivedQty * (float) $transferItem->unit_cost, 2),
                        'reference_doc'   => $locked->transfer_number,
                        'notes'           => 'استلام تحويل داخلي من: '.($locked->fromWarehouse->name ?? '—'),
                        'status'          => 'completed',
                    ]);
                }

                $locked->update([
                    'status'      => 'completed',
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::log('استلام تحويل', "تم استلام التحويل رقم: {$transfer->transfer_number}");

        return back()->with('success', __('تم استلام التحويل وتحديث أرصدة المستودع الوجهة بنجاح.'));
    }
}
