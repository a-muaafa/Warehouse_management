<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\MovementDocument;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $transactionsQuery = Transaction::with(['item.department', 'item.primaryUnit', 'user', 'warehouse', 'supplier', 'customer', 'documents']);

        // Department isolation for non-admin users
        if (! $user->isAdmin()) {
            if ($user->department_id) {
                $transactionsQuery->whereHas('item', fn ($sq) => $sq->where('department_id', $user->department_id));
            } elseif ($user->isWorker()) {
                $transactionsQuery->where('user_id', $user->id);
            }
        }

        $transactions = $transactionsQuery
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('movement_reason'), fn ($q) => $q->where('movement_reason', $request->movement_reason))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date))
            ->latest()
            ->paginate(20);

        // Filter items list by department for non-admin users
        $itemsQuery = Item::query();
        if (! $user->isAdmin() && $user->department_id) {
            $itemsQuery->where('department_id', $user->department_id);
        }
        $items = $itemsQuery->orderBy('name')->get();

        $warehouses = Warehouse::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $units = Unit::where('is_active', true)->get();

        return view('transactions.index', compact('transactions', 'items', 'warehouses', 'suppliers', 'customers', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Enforce worker permissions
        if ($user->isViewOnlyWorker()) {
            return back()->with('error', __('حسابك مخصص للعرض فقط، لا يمكنك تسجيل عمليات إدخال أو صرف.'));
        }

        if ($user->isAuditorWorker()) {
            return back()->with('error', __('حسابك مخصص للجرد الحراري والفرز، لا يمتلك صلاحية تسجيل الوارد والمنصرف المباشر.'));
        }

        if (! $user->canCreateTransactions()) {
            return back()->with('error', __('ليس لديك صلاحية لإجراء حركات المخزون.'));
        }

        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'type' => ['required', 'in:in,out'],
            'movement_reason' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'reference_doc' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Department isolation check on item creation
        if (! $user->isAdmin() && $user->department_id && $item->department_id !== $user->department_id) {
            return back()->with('error', __('غير مسموح لك بإجراء عمليات على أصناف تابعة لأقسام أخرى.'));
        }

        $qty = (float) $validated['quantity'];

        // Unit conversion if secondary unit selected
        if (! empty($validated['unit_id'])) {
            $selectedUnit = Unit::find($validated['unit_id']);
            if ($selectedUnit) {
                $qty = $selectedUnit->toBase($qty);
            }
        }

        $unitPrice = isset($validated['unit_price']) && $validated['unit_price'] > 0
            ? (float) $validated['unit_price']
            : ($validated['type'] === 'in' ? (float) $item->purchase_price : (float) $item->selling_price);

        if ($validated['type'] === 'out' && $item->quantity < $qty) {
            return back()->withErrors(['quantity' => __('الكمية المتوفرة بالمخزون غير كافية. المتوفر: :qty', ['qty' => $item->quantity])]);
        }

        DB::transaction(function () use ($validated, $item, $qty, $unitPrice, $request) {
            $batchId = null;

            if ($validated['type'] === 'in') {
                // Inbound receipt: create new lot/batch if batch number provided
                $batchNumber = ! empty($validated['batch_number']) ? $validated['batch_number'] : 'LOT-'.date('Ymd').'-'.strtoupper(uniqid());
                $batch = ItemBatch::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'batch_number' => $batchNumber,
                    'purchase_cost' => $unitPrice,
                    'quantity_received' => $qty,
                    'quantity_remaining' => $qty,
                    'expiry_date' => $validated['expiry_date'] ?? null,
                    'status' => 'active',
                ]);
                $batchId = $batch->id;

                $item->increment('quantity', (int) $qty);
                if ($unitPrice > 0) {
                    $item->purchase_price = $unitPrice;
                    $item->save();
                }
            } else {
                // Outbound: allocate via FIFO/LIFO valuation service
                $this->valuationService->allocateOutboundStock($item, $qty, $validated['warehouse_id'] ?? null);
                $item->decrement('quantity', (int) $qty);
            }

            $transaction = Transaction::create([
                'item_id' => $item->id,
                'user_id' => $request->user()->id,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'item_batch_id' => $batchId,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'unit_id' => $validated['unit_id'] ?? $item->unit_id,
                'type' => $validated['type'],
                'movement_reason' => $validated['movement_reason'] ?? ($validated['type'] === 'in' ? 'purchase' : 'sale_delivery'),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_amount' => $qty * $unitPrice,
                'reference_doc' => $validated['reference_doc'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
            ]);

            // Save linked movement document if document number provided
            if (! empty($validated['reference_doc'])) {
                MovementDocument::create([
                    'transaction_id' => $transaction->id,
                    'document_type' => $validated['document_type'] ?? ($validated['type'] === 'in' ? 'delivery_note' : 'delivery_note'),
                    'document_number' => $validated['reference_doc'],
                    'reference_party' => $validated['supplier_id'] ? 'المورد #'.$validated['supplier_id'] : ($validated['customer_id'] ? 'العميل #'.$validated['customer_id'] : null),
                    'notes' => $validated['notes'] ?? null,
                ]);
            }
        });

        $actionType = $validated['type'] === 'in' ? 'توثيق حركة وارد' : 'توثيق حركة منصرف';
        ActivityLog::log($actionType, "تم توثيق حركة (الكمية: {$qty}) للصنف: {$item->name}", $item->department_id);

        $message = $validated['type'] === 'in' ? __('تم تسجيل عملية التوريد (الوارد) بنجاح.') : __('تم تسجيل عملية الصرف (المنصرف) بنجاح.');

        if ($validated['type'] === 'in') {
            return redirect()->route('worker.labels.print', ['item' => $item->id])
                ->with('success', $message.' '.__('يمكنك طباعة ملصق الباركود للصنف أدناه.'));
        }

        return back()->with('success', $message);
    }
}
