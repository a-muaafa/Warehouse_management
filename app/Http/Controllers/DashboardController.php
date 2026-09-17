<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\HoldingCostCalculatorService;
use App\Services\InventoryValuationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService,
        protected HoldingCostCalculatorService $holdingCostService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. High-Level KPI Summary
        $valuationData = $this->valuationService->getInventoryValuationSummary();
        $totalHoldingCost = Item::all()->sum(fn ($item) => $this->holdingCostService->calculateItemHoldingCost($item)['monthly_cost']);

        $stats = [
            'warehouses' => Warehouse::where('is_active', true)->count(),
            'departments' => Department::count(),
            'supervisors' => User::where('role', 'supervisor')->count(),
            'workers' => User::where('role', 'worker')->count(),
            'items' => Item::count(),
            'total_valuation' => $valuationData['total_valuation'],
            'total_holding_cost' => $totalHoldingCost,
            'low_stock' => Item::whereColumn('quantity', '<=', 'min_alert_level')->count(),
            'reorder_alerts' => Item::whereColumn('quantity', '<=', 'reorder_point')->count(),
            'expiring_batches' => ItemBatch::expiringSoon(30)->count(),
            'expired_batches' => ItemBatch::expired()->count(),
            'transactions_today' => Transaction::whereDate('created_at', today())->count(),
        ];

        // 2. High-Turnover (Fast-Moving) Items: highest out volume in last 30 days
        $fastMovingItems = Item::with('primaryUnit', 'department')
            ->withCount(['transactions as outbound_volume' => function ($q) {
                $q->where('type', 'out')->where('created_at', '>=', now()->subDays(30));
            }])
            ->orderBy('outbound_volume', 'desc')
            ->take(5)
            ->get();

        // 3. Stagnant / Dead Stock Items: items with no transactions in 30 days and quantity > 0
        $stagnantItems = Item::with('primaryUnit', 'department')
            ->where('quantity', '>', 0)
            ->whereDoesntHave('transactions', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })
            ->take(5)
            ->get();

        // 4. Low Stock and Expiring Alert Items
        $lowStockItems = Item::with('department', 'primaryUnit')
            ->whereColumn('quantity', '<=', 'min_alert_level')
            ->latest()
            ->take(6)
            ->get();

        $expiringBatches = ItemBatch::with('item', 'warehouse')
            ->expiringSoon(30)
            ->orderBy('expiry_date', 'asc')
            ->take(6)
            ->get();

        // 5. Recent Transactions Feed
        $recentTransactions = Transaction::with(['item', 'user', 'warehouse', 'documents'])
            ->latest()
            ->take(8)
            ->get();

        // 6. Warehouses stock status
        $warehousesList = Warehouse::withCount(['batches as active_batches_count' => fn ($q) => $q->where('status', 'active')])
            ->get();

        return view('dashboard', compact(
            'stats',
            'fastMovingItems',
            'stagnantItems',
            'lowStockItems',
            'expiringBatches',
            'recentTransactions',
            'warehousesList'
        ));
    }
}
