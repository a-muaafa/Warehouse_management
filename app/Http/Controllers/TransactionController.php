<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\InventoryAudit;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\MovementDocument;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transaction::class);

        $user = $request->user();

        $transactionsQuery = Transaction::with(['item.department', 'item.primaryUnit', 'user', 'warehouse', 'supplier', 'customer', 'documents', 'batchItems.batch']);

        // Warehouse & department isolation for non-super-admin users
        if (! $user->isSuperAdmin()) {
            $accessibleWarehouseIds = $user->accessibleWarehouseIds();
            if (! empty($accessibleWarehouseIds)) {
                $transactionsQuery->where(function ($q) use ($accessibleWarehouseIds) {
                    $q->whereIn('warehouse_id', $accessibleWarehouseIds)
                        ->orWhereNull('warehouse_id');
                });
            }

            if ($user->department_id) {
                $transactionsQuery->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id));
            }
        }

        $transactions = $transactionsQuery
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('movement_reason'), fn ($q) => $q->where('movement_reason', $request->movement_reason))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date))
            ->latest()
            ->paginate(20);

        // Filter items list by department for non-super-admin users
        $itemsQuery = Item::query();
        if (! $user->isSuperAdmin() && $user->department_id) {
            $itemsQuery->where('department_id', $user->department_id);
        }
        $items = $itemsQuery->orderBy('name')->get();

        $warehousesQuery = Warehouse::where('is_active', true);
        if (! $user->isSuperAdmin()) {
            $warehousesQuery->whereIn('id', $user->accessibleWarehouseIds());
        }
        $warehouses = $warehousesQuery->get();

        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();

        return view('transactions.index', compact('transactions', 'items', 'warehouses', 'suppliers', 'customers', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validation
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'type' => ['required', 'in:in,out'],
            'movement_reason' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'manufacture_date' => ['nullable', 'date'],
            'reference_doc' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'unique:transactions,idempotency_key'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Permission checks based on type
        if ($validated['type'] === 'in') {
            if (! $user->hasPermission('stock.receive')) {
                abort(403, __('ليس لديك صلاحية لتسجيل عمليات التوريد (الوارد).'));
            }
        } else {
            if (! $user->hasPermission('stock.issue')) {
                abort(403, __('ليس لديك صلاحية لتسجيل عمليات الصرف (المنصرف).'));
            }
        }

        // Warehouse-level access check
        if (! empty($validated['warehouse_id']) && ! $user->canAccessWarehouse((int) $validated['warehouse_id'])) {
            abort(403, __('غير مصرح لك بإجراء حركات في مستودع لا يقع ضمن صلاحياتك.'));
        }

        // Check if warehouse has an active audit with frozen movements
        if (! empty($validated['warehouse_id'])) {
            $frozenAudit = InventoryAudit::where('warehouse_id', $validated['warehouse_id'])
                ->whereIn('status', ['in_progress', 'pending_approval'])
                ->where('freeze_movements', true)
                ->exists();

            if ($frozenAudit) {
                return back()->withErrors(['warehouse_id' => __('حركة المخزون مجمدة مؤقتاً في هذا المستودع بسبب وجود جرد نشط.')]);
            }
        }

        $item = Item::findOrFail($validated['item_id']);

        // Department isolation: non-super-admin users can only operate on their department's items
        if (! $user->isSuperAdmin() && $user->department_id && $item->department_id !== $user->department_id) {
            return back()->with('error', __('غير مسموح لك بإجراء عمليات على أصناف تابعة لأقسام أخرى.'));
        }

        // Unit conversion: resolve the quantity to the base unit BEFORE stock checks
        $qty = (float) $validated['quantity'];

        if (! empty($validated['unit_id'])) {
            $selectedUnit = Unit::find($validated['unit_id']);
            $isValidUnit = $selectedUnit && in_array($selectedUnit->id, array_filter([
                $item->unit_id,
                $item->secondary_unit_id,
            ]));

            if ($selectedUnit && $isValidUnit) {
                $qty = $selectedUnit->toBase($qty);
            } elseif ($selectedUnit && ! $isValidUnit) {
                return back()->withErrors(['unit_id' => __('وحدة القياس المحددة غير مرتبطة بهذا الصنف.')]);
            }
        }

        // Unit price — prefer explicit input, fall back to item's catalogue price
        $unitPrice = isset($validated['unit_price']) && $validated['unit_price'] > 0
            ? (float) $validated['unit_price']
            : ($validated['type'] === 'in' ? (float) $item->purchase_price : (float) $item->selling_price);

        try {
            $transactionResult = DB::transaction(function () use ($validated, $item, $qty, $unitPrice, $user) {
                $lockedItem = Item::where('id', $item->id)->lockForUpdate()->firstOrFail();
                $qtyBefore = (float) $lockedItem->quantity;
                $allocations = [];
                $batchId = null;

                if ($validated['type'] === 'out') {
                    if ($qtyBefore < $qty) {
                        throw new \DomainException(
                            __('الكمية المتوفرة بالمخزون غير كافية. المتوفر: :qty', ['qty' => $lockedItem->quantity])
                        );
                    }

                    $allocations = $this->valuationService->allocateOutboundStock(
                        $lockedItem,
                        $qty,
                        $validated['warehouse_id'] ?? null
                    );

                    $lockedItem->decrement('quantity', $qty);
                    // When only a single batch was used, also populate item_batch_id for convenience
                    $batchId = count($allocations) === 1 ? $allocations[0]['batch_id'] : null;
                } else {
                    $batchNumber = ! empty($validated['batch_number'])
                        ? $validated['batch_number']
                        : 'LOT-'.date('Ymd').'-'.strtoupper(uniqid());

                    $batch = ItemBatch::create([
                        'item_id' => $lockedItem->id,
                        'warehouse_id' => $validated['warehouse_id'] ?? null,
                        'supplier_id' => $validated['supplier_id'] ?? null,
                        'batch_number' => $batchNumber,
                        'purchase_cost' => $unitPrice,
                        'quantity_received' => $qty,
                        'quantity_remaining' => $qty,
                        'manufacture_date' => $validated['manufacture_date'] ?? null,
                        'expiry_date' => $validated['expiry_date'] ?? null,
                        'received_at' => now(),
                        'status' => 'active',
                    ]);
                    $batchId = $batch->id;

                    $lockedItem->increment('quantity', $qty);

                    if ($unitPrice > 0) {
                        $lockedItem->purchase_price = $unitPrice;
                        $lockedItem->save();
                    }
                }

                $transaction = Transaction::create([
                    'item_id' => $lockedItem->id,
                    'user_id' => $user->id,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'item_batch_id' => $batchId,
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'unit_id' => $validated['unit_id'] ?? $lockedItem->unit_id,
                    'type' => $validated['type'],
                    'movement_reason' => $validated['movement_reason']
                        ?? ($validated['type'] === 'in' ? 'purchase' : 'sale_delivery'),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => round($qty * $unitPrice, 2),
                    'reference_doc' => $validated['reference_doc'] ?? null,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'completed',
                ]);

                if ($validated['type'] === 'in' && isset($batch)) {
                    $batch->update(['transaction_id' => $transaction->id]);

                    TransactionBatchItem::create([
                        'transaction_id' => $transaction->id,
                        'item_batch_id' => $batch->id,
                        'quantity' => $qty,
                        'unit_cost' => $unitPrice,
                        'total_cost' => round($qty * $unitPrice, 4),
                    ]);

                    InventoryMovement::record([
                        'reference_number' => $transaction->reference_doc ?: ('TX-'.$transaction->id),
                        'movement_type' => 'receipt',
                        'source_type' => Transaction::class,
                        'source_id' => $transaction->id,
                        'item_id' => $lockedItem->id,
                        'warehouse_id' => $validated['warehouse_id'] ?? null,
                        'item_batch_id' => $batch->id,
                        'quantity_before' => $qtyBefore,
                        'quantity_change' => $qty,
                        'quantity_after' => round($qtyBefore + $qty, 4),
                        'unit_cost' => $unitPrice,
                        'total_cost' => round($qty * $unitPrice, 4),
                        'performed_by' => $user->id,
                        'notes' => $validated['notes'] ?? 'Inbound receipt',
                    ]);
                } else {
                    $runningBefore = $qtyBefore;
                    foreach ($allocations as $alloc) {
                        TransactionBatchItem::create([
                            'transaction_id' => $transaction->id,
                            'item_batch_id' => $alloc['batch_id'],
                            'quantity' => $alloc['quantity'],
                            'unit_cost' => $alloc['unit_cost'],
                            'total_cost' => $alloc['total_cost'],
                        ]);

                        $runningAfter = round($runningBefore - (float) $alloc['quantity'], 4);
                        InventoryMovement::record([
                            'reference_number' => $transaction->reference_doc ?: ('TX-'.$transaction->id),
                            'movement_type' => 'issue',
                            'source_type' => Transaction::class,
                            'source_id' => $transaction->id,
                            'item_id' => $lockedItem->id,
                            'warehouse_id' => $validated['warehouse_id'] ?? null,
                            'item_batch_id' => $alloc['batch_id'],
                            'quantity_before' => $runningBefore,
                            'quantity_change' => -(float) $alloc['quantity'],
                            'quantity_after' => $runningAfter,
                            'unit_cost' => $alloc['unit_cost'],
                            'total_cost' => $alloc['total_cost'],
                            'performed_by' => $user->id,
                            'notes' => $validated['notes'] ?? "Outbound issue from batch {$alloc['batch_number']}",
                        ]);
                        $runningBefore = $runningAfter;
                    }
                }

                if (! empty($validated['reference_doc'])) {
                    MovementDocument::create([
                        'transaction_id' => $transaction->id,
                        'document_type' => $validated['document_type']
                            ?? ($validated['type'] === 'in' ? 'delivery_note' : 'delivery_note'),
                        'document_number' => $validated['reference_doc'],
                        'reference_party' => $validated['supplier_id']
                            ? 'المورد #'.$validated['supplier_id']
                            : ($validated['customer_id'] ? 'العميل #'.$validated['customer_id'] : null),
                        'notes' => $validated['notes'] ?? null,
                    ]);
                }

                return ['transaction' => $transaction, 'item' => $lockedItem, 'allocations' => $allocations];
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        Cache::forget('dashboard_kpis');

        $item = $transactionResult['item'];
        $tx = $transactionResult['transaction'];
        $actionType = $validated['type'] === 'in' ? 'توثيق حركة وارد' : 'توثيق حركة منصرف';
        ActivityLog::log(
            $actionType,
            "تم توثيق حركة (الكمية: {$qty}) للصنف: {$item->name}",
            $item->department_id,
            $tx,
            null,
            ['type' => $validated['type'], 'quantity' => $qty, 'warehouse_id' => $validated['warehouse_id'] ?? null]
        );

        $message = $validated['type'] === 'in'
            ? __('تم تسجيل عملية التوريد (الوارد) بنجاح.')
            : __('تم تسجيل عملية الصرف (المنصرف) بنجاح.');

        if ($validated['type'] === 'in') {
            return redirect()->route('worker.labels.print', ['item' => $item->id])
                ->with('success', $message.' '.__('يمكنك طباعة ملصق الباركود للصنف أدناه.'));
        }

        return back()->with('success', $message);
    }
}
