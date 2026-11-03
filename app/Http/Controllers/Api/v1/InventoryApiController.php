<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryApiController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    /**
     * Get real-time stock balances across all items and warehouses with filtering, sorting, and pagination.
     */
    public function stockBalances(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'low_stock' => ['nullable', 'in:0,1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:name,quantity,purchase_price,selling_price,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $perPage = (int) $request->input('per_page', 50);
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        $items = Item::with(['primaryUnit', 'category', 'department', 'batches' => function ($q) use ($request) {
            $q->active()->when($request->filled('warehouse_id'), fn ($bq) => $bq->where('warehouse_id', $request->integer('warehouse_id')));
        }])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->whereHas('batches', fn ($bq) => $bq->active()->where('warehouse_id', $request->integer('warehouse_id'))))
            ->when($request->input('low_stock') === '1', fn ($q) => $q->whereColumn('quantity', '<=', 'min_alert_level'))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $s = $request->input('search');
                $sub->where('name', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Stock balances retrieved successfully',
            'data' => $items,
        ]);
    }

    /**
     * Get item details by barcode or SKU.
     */
    public function findByBarcode(string $code): JsonResponse
    {
        $item = Item::with(['primaryUnit', 'category', 'batches' => fn ($q) => $q->active()])
            ->where('barcode', $code)
            ->orWhere('sku', $code)
            ->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Item not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Item retrieved successfully',
            'data' => $item,
            'item' => $item,
        ]);
    }

    /**
     * Get valuation & holding cost summary for ERP / Financial modules.
     */
    public function valuationSummary(Request $request): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $summary = $this->valuationService->getInventoryValuationSummary($warehouseId);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Inventory valuation summary retrieved successfully',
            'data' => $summary,
            'valuation' => $summary,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Ingest external ERP transactions (Inbound PO receipt / Outbound Sales delivery).
     */
    public function recordExternalTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'exists:items,barcode'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'warehouse_code' => ['nullable', 'string', 'exists:warehouses,code'],
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'movement_reason' => ['required', 'string', 'max:255'],
            'reference_doc' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'unique:transactions,idempotency_key'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? null;
        if (! $warehouseId && ! empty($validated['warehouse_code'])) {
            $warehouseId = Warehouse::where('code', $validated['warehouse_code'])->value('id');
        }

        // If authenticated via a Sanctum user token, enforce warehouse-level access
        $user = $request->user();
        if ($user && $warehouseId && ! $user->canAccessWarehouse((int) $warehouseId)) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Unauthorized access to the specified warehouse.',
                'data' => null,
            ], 403);
        }

        $qty = (float) $validated['quantity'];

        try {
            $result = DB::transaction(function () use ($validated, $qty, $warehouseId, $request, $user) {
                /** @var Item $item */
                $item = Item::where('barcode', $validated['barcode'])->lockForUpdate()->firstOrFail();
                $qtyBefore = (float) $item->quantity;
                $allocations = [];
                $batchId = null;

                if ($validated['type'] === 'out') {
                    $allocations = $this->valuationService->allocateOutboundStock($item, $qty, $warehouseId);
                    $item->decrement('quantity', $qty);
                    $batchId = count($allocations) === 1 ? $allocations[0]['batch_id'] : null;

                    $unitPrice = (float) ($validated['unit_price']
                        ?? collect($allocations)->avg('unit_cost')
                        ?? (float) $item->selling_price);
                } else {
                    $unitPrice = (float) ($validated['unit_price'] ?? (float) $item->purchase_price);

                    $batch = ItemBatch::create([
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouseId,
                        'batch_number' => 'ERP-'.now()->format('Ymd').'-'.strtoupper(uniqid()),
                        'purchase_cost' => $unitPrice,
                        'quantity_received' => $qty,
                        'quantity_remaining' => $qty,
                        'received_at' => now(),
                        'status' => 'active',
                    ]);
                    $batchId = $batch->id;

                    $item->increment('quantity', $qty);
                }

                $tokenName = $request->attributes->get('api_token')['name'] ?? 'Unknown API Client';

                $transaction = Transaction::create([
                    'item_id' => $item->id,
                    'user_id' => $user?->id,
                    'warehouse_id' => $warehouseId,
                    'item_batch_id' => $batchId,
                    'type' => $validated['type'],
                    'movement_reason' => $validated['movement_reason'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount' => round($qty * $unitPrice, 2),
                    'reference_doc' => $validated['reference_doc'] ?? null,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                    'notes' => ($validated['notes'] ?? 'ERP API Sync')." | Client: {$tokenName}",
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
                        'reference_number' => $transaction->reference_doc ?: ('API-'.$transaction->id),
                        'movement_type' => 'receipt',
                        'source_type' => Transaction::class,
                        'source_id' => $transaction->id,
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouseId,
                        'item_batch_id' => $batch->id,
                        'quantity_before' => $qtyBefore,
                        'quantity_change' => $qty,
                        'quantity_after' => round($qtyBefore + $qty, 4),
                        'unit_cost' => $unitPrice,
                        'total_cost' => round($qty * $unitPrice, 4),
                        'performed_by' => $user?->id,
                        'notes' => "API Sync ({$tokenName})",
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
                            'reference_number' => $transaction->reference_doc ?: ('API-'.$transaction->id),
                            'movement_type' => 'issue',
                            'source_type' => Transaction::class,
                            'source_id' => $transaction->id,
                            'item_id' => $item->id,
                            'warehouse_id' => $warehouseId,
                            'item_batch_id' => $alloc['batch_id'],
                            'quantity_before' => $runningBefore,
                            'quantity_change' => -(float) $alloc['quantity'],
                            'quantity_after' => $runningAfter,
                            'unit_cost' => $alloc['unit_cost'],
                            'total_cost' => $alloc['total_cost'],
                            'performed_by' => $user?->id,
                            'notes' => "API Outbound ({$tokenName})",
                        ]);
                        $runningBefore = $runningAfter;
                    }
                }

                return ['transaction' => $transaction, 'item' => $item];
            });

            Cache::forget('dashboard_kpis');

            $newBalance = (float) $result['item']->fresh()->quantity;

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => [
                    'id' => $result['transaction']->id,
                    'type' => $validated['type'] === 'in' ? 'stock_in' : 'stock_out',
                    'warehouse_id' => $warehouseId,
                    'new_balance' => $newBalance,
                ],
                'transaction_id' => $result['transaction']->id,
                'warehouse_id' => $warehouseId,
                'new_balance' => $newBalance,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}
