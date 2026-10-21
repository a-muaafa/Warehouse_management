<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBatch;
use Illuminate\Support\Facades\DB;

class InventoryValuationService
{
    /**
     * Deduct outbound stock according to the item's valuation method (FIFO / LIFO / FEFO).
     *
     * IMPORTANT: This method MUST be called inside a DB::transaction() with appropriate
     * lockForUpdate() applied to the parent record so concurrent requests cannot
     * double-deduct the same batch quantities.
     *
     * Throws \DomainException if the requested quantity cannot be fully satisfied —
     * a partial allocation is NEVER committed silently.
     *
     * @return array<int, array{batch_id: int, batch_number: string, quantity: float, unit_cost: float, total_cost: float}>
     *
     * @throws \DomainException when available batch stock is insufficient
     */
    public function allocateOutboundStock(Item $item, float $requestedQuantity, ?int $warehouseId = null): array
    {
        if ($requestedQuantity <= 0) {
            throw new \DomainException('Requested quantity must be positive.');
        }

        $method = $item->valuation_method ?: 'fifo';

        $query = ItemBatch::where('item_id', $item->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->lockForUpdate();          // Prevent concurrent deductions from the same batches

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // FIFO: earliest receipt first; FEFO: earliest expiry first; LIFO: latest receipt first
        if ($method === 'lifo') {
            $query->orderBy('created_at', 'desc');
        } elseif ($item->is_perishable) {
            // FEFO — null expiry dates go to the back
            $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('created_at', 'asc');
        } else {
            // FIFO
            $query->orderBy('created_at', 'asc');
        }

        $batches = $query->get();

        // Pre-flight check: ensure we can satisfy the full quantity BEFORE modifying any batch.
        // This prevents partial allocations that would leave inventory in an inconsistent state.
        $totalAvailable = $batches->sum(fn ($b) => (float) $b->quantity_remaining);

        if ($totalAvailable < $requestedQuantity) {
            $context = $warehouseId ? " in warehouse #{$warehouseId}" : '';
            throw new \DomainException(
                "Insufficient batch stock for item '{$item->name}'{$context}. "
                . "Requested: {$requestedQuantity}, Available: {$totalAvailable}"
            );
        }

        $allocations = [];
        $remainingToDeduct = $requestedQuantity;

        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            $deductQty = min((float) $batch->quantity_remaining, $remainingToDeduct);

            $batch->quantity_remaining = round((float) $batch->quantity_remaining - $deductQty, 4);

            if ($batch->quantity_remaining <= 0) {
                $batch->quantity_remaining = 0;
                $batch->status = 'depleted';
            }

            $batch->save();

            $allocations[] = [
                'batch_id'    => $batch->id,
                'batch_number'=> $batch->batch_number,
                'quantity'    => $deductQty,
                'unit_cost'   => (float) $batch->purchase_cost,
                'total_cost'  => round($deductQty * (float) $batch->purchase_cost, 2),
            ];

            $remainingToDeduct = round($remainingToDeduct - $deductQty, 4);
        }

        return $allocations;
    }

    /**
     * Calculate total inventory capital tied-up and valuation breakdown.
     *
     * When $warehouseId is provided, only batches in that warehouse are counted —
     * item counts are also scoped to items that have at least one active batch there.
     *
     * @return array{total_valuation: float, total_items_count: int, total_units_count: float, fifo_value: float, avg_unit_cost: float}
     */
    public function getInventoryValuationSummary(?int $warehouseId = null): array
    {
        $batchQuery = ItemBatch::where('status', 'active')->where('quantity_remaining', '>', 0);

        if ($warehouseId) {
            $batchQuery->where('warehouse_id', $warehouseId);
        }

        $totalValuation = (float) (clone $batchQuery)
            ->selectRaw('SUM(quantity_remaining * purchase_cost) as total')
            ->value('total') ?: 0.0;

        $totalUnits = (float) (clone $batchQuery)->sum('quantity_remaining') ?: 0.0;

        // Count only items that have at least one active batch in scope
        $totalItems = (clone $batchQuery)
            ->distinct('item_id')
            ->count('item_id');

        return [
            'total_valuation'  => round($totalValuation, 2),
            'total_items_count'=> $totalItems,
            'total_units_count'=> $totalUnits,
            'fifo_value'       => round($totalValuation, 2),  // Same as total_valuation (FIFO = current active batch cost)
            'avg_unit_cost'    => $totalUnits > 0 ? round($totalValuation / $totalUnits, 4) : 0.0,
        ];
    }
}
