<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Services\InventoryValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryApiController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    /**
     * Get real-time stock balances across all items and warehouses.
     */
    public function stockBalances(Request $request): JsonResponse
    {
        $request->validate([
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:200'],
            'category_id'  => ['nullable', 'integer', 'exists:item_categories,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'low_stock'    => ['nullable', 'in:0,1'],
        ]);

        $perPage = (int) $request->input('per_page', 50);

        $items = Item::with(['primaryUnit', 'category', 'department', 'batches' => function ($q) use ($request) {
            $q->active()->when($request->filled('warehouse_id'), fn ($bq) => $bq->where('warehouse_id', $request->integer('warehouse_id')));
        }])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->whereHas('batches', fn ($bq) => $bq->active()->where('warehouse_id', $request->integer('warehouse_id'))))
            ->when($request->input('low_stock') === '1', fn ($q) => $q->whereColumn('quantity', '<=', 'min_alert_level'))
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $items,
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
            return response()->json(['status' => 'error', 'message' => 'Item not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'item'   => $item,
        ]);
    }

    /**
     * Get valuation & holding cost summary for ERP / Financial modules.
     */
    public function valuationSummary(): JsonResponse
    {
        $summary = $this->valuationService->getInventoryValuationSummary();

        return response()->json([
            'status'    => 'success',
            'valuation' => $summary,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Ingest external ERP transactions (Inbound PO receipt / Outbound Sales delivery).
     *
     * SECURITY: This endpoint requires a write-scoped API token.
     * It never falls back to user_id=1 — unauthenticated requests are rejected at the middleware layer.
     */
    public function recordExternalTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'         => ['required', 'string', 'exists:items,barcode'],
            'warehouse_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
            'warehouse_code'  => ['nullable', 'string', 'exists:warehouses,code'],
            'type'            => ['required', 'in:in,out'],
            'quantity'        => ['required', 'numeric', 'min:0.001'],
            'unit_price'      => ['nullable', 'numeric', 'min:0'],
            'movement_reason' => ['required', 'string', 'max:255'],
            'reference_doc'   => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? null;
        if (! $warehouseId && ! empty($validated['warehouse_code'])) {
            $warehouseId = \App\Models\Warehouse::where('code', $validated['warehouse_code'])->value('id');
        }

        $qty = (float) $validated['quantity'];

        try {
            $result = DB::transaction(function () use ($validated, $qty, $warehouseId, $request) {
                /** @var Item $item */
                $item = Item::where('barcode', $validated['barcode'])->lockForUpdate()->firstOrFail();

                if ($validated['type'] === 'out') {
                    // Verify sufficient stock exists in this warehouse BEFORE deducting any batches
                    $availableQuery = ItemBatch::where('item_id', $item->id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0);

                    if ($warehouseId) {
                        $availableQuery->where('warehouse_id', $warehouseId);
                    }

                    $available = (float) $availableQuery->sum('quantity_remaining');

                    if ($available < $qty) {
                        $context = $warehouseId ? " in warehouse #{$warehouseId}" : '';
                        throw new \DomainException(
                            "Insufficient batch stock{$context}. Requested: {$qty}, Available: {$available}"
                        );
                    }

                    $allocations = $this->valuationService->allocateOutboundStock($item, $qty, $warehouseId);
                    $item->decrement('quantity', $qty);

                    $unitPrice = (float) ($validated['unit_price']
                        ?? collect($allocations)->avg('unit_cost')
                        ?? (float) $item->selling_price);
                } else {
                    // Inbound: create a batch to maintain audit trail tied to the specific warehouse
                    $unitPrice = (float) ($validated['unit_price'] ?? (float) $item->purchase_price);

                    ItemBatch::create([
                        'item_id'            => $item->id,
                        'warehouse_id'       => $warehouseId,
                        'batch_number'       => 'ERP-'.now()->format('Ymd').'-'.strtoupper(uniqid()),
                        'purchase_cost'      => $unitPrice,
                        'quantity_received'  => $qty,
                        'quantity_remaining' => $qty,
                        'status'             => 'active',
                    ]);

                    $item->increment('quantity', $qty);
                }

                // Resolve the API client's identity from the token metadata (set by AuthApiToken middleware).
                // There is NO fallback to any user ID — the token metadata must carry a name.
                $tokenName = $request->attributes->get('api_token')['name'] ?? 'Unknown API Client';

                $transaction = Transaction::create([
                    'item_id'         => $item->id,
                    'user_id'         => null, // External API — no web user; tracked via reference_doc & notes
                    'warehouse_id'    => $warehouseId,
                    'type'            => $validated['type'],
                    'movement_reason' => $validated['movement_reason'],
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'total_amount'    => round($qty * $unitPrice, 2),
                    'reference_doc'   => $validated['reference_doc'] ?? null,
                    'notes'           => ($validated['notes'] ?? 'ERP API Sync') . " | Client: {$tokenName}",
                    'status'          => 'completed',
                ]);

                return ['transaction' => $transaction, 'item' => $item];
            });

            return response()->json([
                'status'         => 'success',
                'message'        => 'Transaction synchronized successfully',
                'transaction_id' => $result['transaction']->id,
                'warehouse_id'   => $warehouseId,
                'new_balance'    => (float) $result['item']->fresh()->quantity,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
