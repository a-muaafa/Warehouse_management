<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBatch;

class InventoryValuationService
{
    /**
     * Deduct outbound stock according to the item's valuation method (FIFO / LIFO / AVG).
     * Returns an array of deducted batches with their allocated quantities and costs.
     *
     * @return array<int, array{batch_id: int, batch_number: string, quantity: float, unit_cost: float, total_cost: float}>
     */
    public function allocateOutboundStock(Item $item, float $requestedQuantity, ?int $warehouseId = null): array
    {
        $method = $item->valuation_method ?: 'fifo';

        $query = ItemBatch::where('item_id', $item->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // FIFO: First created / earliest expiry first
        // LIFO: Latest created first
        if ($method === 'lifo') {
            $query->orderBy('created_at', 'desc');
        } else {
            // FIFO (or FEFO: First Expired, First Out if perishable)
            if ($item->is_perishable) {
                $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')->orderBy('created_at', 'asc');
            } else {
                $query->orderBy('created_at', 'asc');
            }
        }

        $batches = $query->get();
        $allocations = [];
        $remainingToDeduct = $requestedQuantity;

        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            $deductQty = min((float) $batch->quantity_remaining, $remainingToDeduct);
            $batch->quantity_remaining -= $deductQty;

            if ($batch->quantity_remaining <= 0) {
                $batch->status = 'depleted';
            }
            $batch->save();

            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $deductQty,
                'unit_cost' => (float) $batch->purchase_cost,
                'total_cost' => $deductQty * (float) $batch->purchase_cost,
            ];

            $remainingToDeduct -= $deductQty;
        }

        return $allocations;
    }

    /**
     * Calculate total inventory capital tied-up and valuation breakdown
     *
     * @return array{total_valuation: float, total_items_count: int, total_units_count: float, fifo_value: float, avg_value: float}
     */
    public function getInventoryValuationSummary(?int $warehouseId = null): array
    {
        $batchQuery = ItemBatch::where('status', 'active')->where('quantity_remaining', '>', 0);
        if ($warehouseId) {
            $batchQuery->where('warehouse_id', $warehouseId);
        }

        $totalValuation = (float) $batchQuery->selectRaw('SUM(quantity_remaining * purchase_cost) as total')->value('total') ?: 0.0;
        $totalUnits = (float) $batchQuery->sum('quantity_remaining') ?: 0.0;
        $totalItems = Item::count();

        return [
            'total_valuation' => $totalValuation,
            'total_items_count' => $totalItems,
            'total_units_count' => $totalUnits,
            'fifo_value' => $totalValuation,
            'avg_value' => $totalUnits > 0 ? ($totalValuation / $totalUnits) : 0.0,
        ];
    }
}
