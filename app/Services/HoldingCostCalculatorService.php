<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Warehouse;

class HoldingCostCalculatorService
{
    /**
     * Compute estimated monthly and annual storage holding cost for an item or entire warehouse
     *
     * @return array{monthly_cost: float, annual_cost: float, space_cost: float, capital_cost: float}
     */
    public function calculateItemHoldingCost(Item $item, float $annualInterestRate = 0.10): array
    {
        // Capital carrying cost = total valuation * annual rate / 12
        $unitVal = (float) ($item->purchase_price ?: 0);
        $totalStockValue = $item->quantity * $unitVal;
        $monthlyCapitalCost = ($totalStockValue * $annualInterestRate) / 12;

        // Physical space storage cost rate
        $monthlySpaceCost = $item->quantity * (float) ($item->storage_cost_rate ?: 0.50);

        $totalMonthlyCost = $monthlyCapitalCost + $monthlySpaceCost;

        return [
            'monthly_cost' => round($totalMonthlyCost, 2),
            'annual_cost' => round($totalMonthlyCost * 12, 2),
            'space_cost' => round($monthlySpaceCost, 2),
            'capital_cost' => round($monthlyCapitalCost, 2),
        ];
    }

    /**
     * Compute total warehouse holding cost across all inventory
     *
     * @return array{total_monthly_holding: float, total_annual_holding: float, warehouse_overhead_monthly: float}
     */
    public function calculateWarehouseHoldingCost(Warehouse $warehouse): array
    {
        $items = Item::whereHas('batches', fn ($q) => $q->where('warehouse_id', $warehouse->id)->where('status', 'active'))->get();

        $monthlyHolding = 0;
        foreach ($items as $item) {
            $calc = $this->calculateItemHoldingCost($item);
            $monthlyHolding += $calc['monthly_cost'];
        }

        // Daily base overhead rate * 30 days
        $overheadMonthly = (float) $warehouse->holding_cost_rate_per_day * 30;

        return [
            'total_monthly_holding' => round($monthlyHolding + $overheadMonthly, 2),
            'total_annual_holding' => round(($monthlyHolding + $overheadMonthly) * 12, 2),
            'warehouse_overhead_monthly' => round($overheadMonthly, 2),
        ];
    }
}
