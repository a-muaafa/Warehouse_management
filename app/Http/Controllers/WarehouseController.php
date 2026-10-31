<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Warehouse;
use App\Services\HoldingCostCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        protected HoldingCostCalculatorService $holdingCostService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        $user = $request->user();
        $query = Warehouse::with(['department', 'batches' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['batches as active_batches_count' => fn ($q) => $q->where('status', 'active')]);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', $user->accessibleWarehouseIds());
        }

        $warehouses = $query->latest()->paginate(15);

        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        $departments = Department::all();

        return view('warehouses.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Warehouse::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'location_address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'capacity_sqm' => 'nullable|numeric|min:0',
            'holding_cost_rate_per_day' => 'nullable|numeric|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $warehouse = Warehouse::create($validated);

        // Automatically associate creator with the new warehouse if not super_admin
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            $warehouse->users()->attach($user->id);
        }

        return redirect()->route('warehouses.index')->with('success', __('Warehouse created successfully.'));
    }

    public function show(Warehouse $warehouse): View
    {
        Gate::authorize('view', $warehouse);

        $warehouse->load(['department', 'batches.item', 'transactions.item', 'transactions.user']);
        $costSummary = $this->holdingCostService->calculateWarehouseHoldingCost($warehouse);

        return view('warehouses.show', compact('warehouse', 'costSummary'));
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        $departments = Department::all();

        return view('warehouses.edit', compact('warehouse', 'departments'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('update', $warehouse);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code,'.$warehouse->id,
            'location_address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'capacity_sqm' => 'nullable|numeric|min:0',
            'holding_cost_rate_per_day' => 'nullable|numeric|min:0',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $warehouse->update($validated);

        return redirect()->route('warehouses.index')->with('success', __('Warehouse updated successfully.'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        Gate::authorize('delete', $warehouse);

        if ($warehouse->batches()->where('quantity_remaining', '>', 0)->exists()) {
            return back()->with('error', __('Cannot delete warehouse containing active inventory batches.'));
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', __('Warehouse deleted successfully.'));
    }
}
