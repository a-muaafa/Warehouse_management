<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBatch;

class InventoryValuationService
{
    /**
     * Deduct outbound stock according to the item's valuation method (FIFO / LIFO / FEFO / AVG).
     *
     * IMPORTANT: This method MUST be called inside a DB::transaction() with appropriate
     * lockForUpdate() applied to the parent record so concurrent requests cannot
     * double-deduct the same batch quantities.
     *
     * Throws \DomainException if the requested quantity cannot be fully satisfied —
     * a partial allocation is NEVER committed silently.
     *
     * @return array<int, array{batch_id: int, batch_number: string, quantity: float, unit_cost: float, total_cost: float, manufacture_date: string|null, expiry_date: string|null}>
     *
     * @throws \DomainException when available batch stock is insufficient or expired
     */
    public function allocateOutboundStock(Item $item, float $requestedQuantity, ?int $warehouseId = null): array
    {
        if ($requestedQuantity <= 0) {
            throw new \DomainException('Requested quantity must be positive.');
        }

        $method = strtolower($item->valuation_method ?: 'fifo');
        $useFefo = $method === 'fefo' || $item->is_perishable;

        $query = ItemBatch::where('item_id', $item->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->lockForUpdate(); // Prevent concurrent deductions from the same batches

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Exclude expired batches when FEFO or perishable policy applies
        if ($useFefo) {
            $query->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            });
        } else {
            // Even for non-FEFO, if a batch has a past expiry date, do not issue it
            $query->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            });
        }

        // FIFO: earliest receipt first; FEFO: earliest expiry first; LIFO: latest receipt first
        if ($method === 'lifo') {
            $query->orderByRaw('COALESCE(received_at, created_at) DESC')
                ->orderBy('id', 'desc');
        } elseif ($useFefo) {
            // FEFO — null expiry dates go to the back, earliest expiry first
            $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->orderByRaw('COALESCE(received_at, created_at) ASC')
                ->orderBy('id', 'asc');
        } else {
            // FIFO (and default physical batch selection for AVG)
            $query->orderByRaw('COALESCE(received_at, created_at) ASC')
                ->orderBy('id', 'asc');
        }

        $batches = $query->get();

        // Pre-flight check: ensure we can satisfy the full quantity BEFORE modifying any batch.
        $totalAvailable = (float) $batches->sum(fn ($b) => (float) $b->quantity_remaining);

        if ($totalAvailable < $requestedQuantity) {
            $context = $warehouseId ? " in warehouse #{$warehouseId}" : '';
            throw new \DomainException(
                "Insufficient non-expired batch stock for item '{$item->name}'{$context}. "
                ."Requested: {$requestedQuantity}, Available: {$totalAvailable}"
            );
        }

        // Calculate Weighted Average unit cost across all active batches of this item if method is avg/weighted_average
        $weightedAvgCost = null;
        if (in_array($method, ['avg', 'weighted_average'], true)) {
            $totalVal = $batches->sum(fn ($b) => (float) $b->quantity_remaining * (float) $b->purchase_cost);
            $weightedAvgCost = $totalAvailable > 0 ? round($totalVal / $totalAvailable, 4) : (float) $item->purchase_price;
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

            $appliedUnitCost = $weightedAvgCost ?? (float) $batch->purchase_cost;

            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $deductQty,
                'unit_cost' => $appliedUnitCost,
                'actual_batch_cost' => (float) $batch->purchase_cost,
                'total_cost' => round($deductQty * $appliedUnitCost, 4),
                'manufacture_date' => $batch->manufacture_date?->format('Y-m-d'),
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
            ];

            $remainingToDeduct = round($remainingToDeduct - $deductQty, 4);
        }

        return $allocations;
    }

    /**
     * Calculate valuation for a single item under a specific accounting method:
     * - 'actual' / 'fifo': sum of remaining batch quantities * their respective batch purchase_cost
     * - 'weighted_average' / 'avg': remaining quantity * weighted average cost of all historical/active receipts
     * - 'lifo': valuation of remaining layers under LIFO assumption
     *
     * @return array{actual_batch_value: float, fifo_value: float, weighted_average_value: float, weighted_average_unit_cost: float, total_remaining_quantity: float}
     */
    public function calculateItemValuation(Item $item, ?int $warehouseId = null): array
    {
        $activeQuery = ItemBatch::where('item_id', $item->id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0);

        $allReceiptsQuery = ItemBatch::where('item_id', $item->id)
            ->where('quantity_received', '>', 0);

        if ($warehouseId) {
            $activeQuery->where('warehouse_id', $warehouseId);
            $allReceiptsQuery->where('warehouse_id', $warehouseId);
        }

        $activeBatches = $activeQuery->get();
        $allBatches = $allReceiptsQuery->get();

        $remainingQty = (float) $activeBatches->sum(fn ($b) => (float) $b->quantity_remaining);

        // 1. Actual Batch Cost Valuation (exact sum of remaining units * their batch cost)
        $actualBatchValue = (float) $activeBatches->sum(
            fn ($b) => (float) $b->quantity_remaining * (float) $b->purchase_cost
        );

        // 2. FIFO Valuation (ending inventory consists of the latest receipts up to $remainingQty)
        $latestBatches = $allBatches->sortByDesc(fn ($b) => ($b->received_at ?? $b->created_at)?->timestamp ?? 0);
        $fifoRemaining = $remainingQty;
        $fifoValue = 0.0;
        foreach ($latestBatches as $batch) {
            if ($fifoRemaining <= 0) {
                break;
            }
            $take = min((float) $batch->quantity_received, $fifoRemaining);
            $fifoValue += $take * (float) $batch->purchase_cost;
            $fifoRemaining -= $take;
        }
        if ($allBatches->isEmpty() && $remainingQty > 0) {
            $fifoValue = $remainingQty * (float) $item->purchase_price;
        }

        // 3. Weighted Average Cost (WAC) across all received batches for this item
        $totalReceivedQty = (float) $allBatches->sum(fn ($b) => (float) $b->quantity_received);
        $totalReceivedCost = (float) $allBatches->sum(
            fn ($b) => (float) $b->quantity_received * (float) $b->purchase_cost
        );

        $wacUnitCost = $totalReceivedQty > 0
            ? ($totalReceivedCost / $totalReceivedQty)
            : (float) $item->purchase_price;

        $weightedAverageValue = $remainingQty * $wacUnitCost;

        return [
            'actual_batch_value' => round($actualBatchValue, 2),
            'fifo_value' => round($fifoValue, 2),
            'weighted_average_value' => round($weightedAverageValue, 2),
            'weighted_average_unit_cost' => round($wacUnitCost, 4),
            'total_remaining_quantity' => round($remainingQty, 4),
        ];
    }

    /**
     * Calculate total inventory capital tied-up and distinct valuation breakdown.
     *
     * Distinguishes clearly between:
     * - Actual Batch Cost (`actual_batch_value` / `total_valuation`)
     * - FIFO Valuation (`fifo_value`)
     * - Weighted Average Cost Valuation (`weighted_average_value`)
     *
     * @return array{total_valuation: float, actual_batch_value: float, fifo_value: float, weighted_average_value: float, total_items_count: int, total_units_count: float, avg_unit_cost: float}
     */
    public function getInventoryValuationSummary(?int $warehouseId = null): array
    {
        $batchQuery = ItemBatch::where('status', 'active')->where('quantity_remaining', '>', 0);

        if ($warehouseId) {
            $batchQuery->where('warehouse_id', $warehouseId);
        }

        $actualBatchValuation = (float) (clone $batchQuery)
            ->selectRaw('SUM(quantity_remaining * purchase_cost) as total')
            ->value('total') ?: 0.0;

        $totalUnits = (float) (clone $batchQuery)->sum('quantity_remaining') ?: 0.0;

        $itemIds = (clone $batchQuery)
            ->distinct('item_id')
            ->pluck('item_id')
            ->all();

        $totalItems = count($itemIds);

        $fifoTotal = 0.0;
        $weightedAvgTotal = 0.0;

        if ($totalItems > 0) {
            $items = Item::whereIn('id', $itemIds)->get();
            foreach ($items as $item) {
                $itemVal = $this->calculateItemValuation($item, $warehouseId);
                $fifoTotal += $itemVal['fifo_value'];
                $weightedAvgTotal += $itemVal['weighted_average_value'];
            }
        }

        return [
            'total_valuation' => round($actualBatchValuation, 2),
            'actual_batch_value' => round($actualBatchValuation, 2),
            'fifo_value' => round($fifoTotal, 2),
            'weighted_average_value' => round($weightedAvgTotal, 2),
            'total_items_count' => $totalItems,
            'total_units_count' => round($totalUnits, 4),
            'avg_unit_cost' => $totalUnits > 0 ? round($weightedAvgTotal / $totalUnits, 4) : 0.0,
        ];
    }
}
