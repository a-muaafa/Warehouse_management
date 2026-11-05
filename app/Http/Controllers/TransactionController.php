<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\IssueStockAction;
use App\Actions\Inventory\ReceiveStockAction;
use App\Data\IssueStockData;
use App\Data\ReceiveStockData;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\InventoryAudit;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        protected IssueStockAction $issueStock,
        protected ReceiveStockAction $receiveStock,
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

        $unitPrice = isset($validated['unit_price']) && $validated['unit_price'] > 0
            ? (float) $validated['unit_price']
            : null;

        try {
            if ($validated['type'] === 'in') {
                $tx = $this->receiveStock->execute(
                    new ReceiveStockData(
                        itemId: $item->id,
                        warehouseId: isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                        quantity: (string) $qty,
                        reason: $validated['movement_reason'] ?? 'purchase',
                        unitPrice: $unitPrice,
                        supplierId: isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
                        unitId: isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
                        batchNumber: $validated['batch_number'] ?? null,
                        manufactureDate: $validated['manufacture_date'] ?? null,
                        expiryDate: $validated['expiry_date'] ?? null,
                        referenceDoc: $validated['reference_doc'] ?? null,
                        documentType: $validated['document_type'] ?? null,
                        idempotencyKey: $validated['idempotency_key'] ?? null,
                        notes: $validated['notes'] ?? null,
                    ),
                    $user
                );
            } else {
                $tx = $this->issueStock->execute(
                    new IssueStockData(
                        itemId: $item->id,
                        warehouseId: isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                        quantity: (string) $qty,
                        reason: $validated['movement_reason'] ?? 'sale_delivery',
                        unitPrice: $unitPrice,
                        customerId: isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                        unitId: isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
                        referenceDoc: $validated['reference_doc'] ?? null,
                        documentType: $validated['document_type'] ?? null,
                        idempotencyKey: $validated['idempotency_key'] ?? null,
                        notes: $validated['notes'] ?? null,
                    ),
                    $user
                );
            }
        } catch (\DomainException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        $item = $tx->item ?? $item->fresh();
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
