<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Inventory\IssueStockAction;
use App\Actions\Inventory\ReceiveStockAction;
use App\Data\IssueStockData;
use App\Data\ReceiveStockData;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService,
        protected IssueStockAction $issueStock,
        protected ReceiveStockAction $receiveStock,
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

        $item = Item::where('barcode', $validated['barcode'])->firstOrFail();
        $tokenName = $request->attributes->get('api_token')['name'] ?? 'Unknown API Client';
        $notes = ($validated['notes'] ?? 'ERP API Sync')." | Client: {$tokenName}";
        $unitPrice = isset($validated['unit_price']) ? (float) $validated['unit_price'] : null;

        try {
            if ($validated['type'] === 'in') {
                $tx = $this->receiveStock->execute(
                    new ReceiveStockData(
                        itemId: $item->id,
                        warehouseId: $warehouseId ? (int) $warehouseId : null,
                        quantity: (string) $validated['quantity'],
                        reason: $validated['movement_reason'],
                        unitPrice: $unitPrice,
                        batchNumber: 'ERP-'.now()->format('Ymd').'-'.strtoupper(uniqid()),
                        referenceDoc: $validated['reference_doc'] ?? null,
                        idempotencyKey: $validated['idempotency_key'] ?? null,
                        notes: $notes,
                    ),
                    $user
                );
            } else {
                $tx = $this->issueStock->execute(
                    new IssueStockData(
                        itemId: $item->id,
                        warehouseId: $warehouseId ? (int) $warehouseId : null,
                        quantity: (string) $validated['quantity'],
                        reason: $validated['movement_reason'],
                        unitPrice: $unitPrice,
                        referenceDoc: $validated['reference_doc'] ?? null,
                        idempotencyKey: $validated['idempotency_key'] ?? null,
                        notes: $notes,
                    ),
                    $user
                );
            }

            $newBalance = (float) ($tx->item?->quantity ?? $item->fresh()->quantity);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => [
                    'id' => $tx->id,
                    'type' => $validated['type'] === 'in' ? 'stock_in' : 'stock_out',
                    'warehouse_id' => $warehouseId,
                    'new_balance' => $newBalance,
                ],
                'transaction_id' => $tx->id,
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
