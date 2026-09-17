<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\InventoryValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    /**
     * Get real-time stock balances across all items and warehouses
     */
    public function stockBalances(Request $request): JsonResponse
    {
        $items = Item::with(['primaryUnit', 'category', 'department', 'batches' => fn ($q) => $q->active()])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('low_stock') && $request->low_stock == '1', fn ($q) => $q->whereColumn('quantity', '<=', 'min_alert_level'))
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Get item details by barcode or SKU
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
            'item' => $item,
        ]);
    }

    /**
     * Get valuation & holding cost summary for ERP / Financial modules
     */
    public function valuationSummary(): JsonResponse
    {
        $summary = $this->valuationService->getInventoryValuationSummary();

        return response()->json([
            'status' => 'success',
            'valuation' => $summary,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Ingest external ERP transactions (Inbound PO receipt / Outbound Sales delivery)
     */
    public function recordExternalTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|exists:items,barcode',
            'type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'movement_reason' => 'required|string',
            'reference_doc' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $item = Item::where('barcode', $validated['barcode'])->firstOrFail();
        $qty = (float) $validated['quantity'];

        if ($validated['type'] === 'out' && $item->quantity < $qty) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient stock balance'], 422);
        }

        if ($validated['type'] === 'in') {
            $item->increment('quantity', (int) $qty);
        } else {
            $this->valuationService->allocateOutboundStock($item, $qty);
            $item->decrement('quantity', (int) $qty);
        }

        $transaction = Transaction::create([
            'item_id' => $item->id,
            'user_id' => $request->user()?->id ?: 1,
            'type' => $validated['type'],
            'movement_reason' => $validated['movement_reason'],
            'quantity' => $qty,
            'unit_price' => $validated['unit_price'] ?? ($validated['type'] === 'in' ? $item->purchase_price : $item->selling_price),
            'total_amount' => $qty * ($validated['unit_price'] ?? $item->purchase_price),
            'reference_doc' => $validated['reference_doc'] ?? null,
            'notes' => $validated['notes'] ?? 'Synced via ERP API Integration',
            'status' => 'completed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction synchronized successfully',
            'transaction_id' => $transaction->id,
            'new_balance' => $item->fresh()->quantity,
        ]);
    }
}
