<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::with('baseUnit')->withCount('items')->latest()->paginate(15);

        return view('units.index', compact('units'));
    }

    public function create(): View
    {
        $baseUnits = Unit::whereNull('base_unit_id')->get();

        return view('units.create', compact('baseUnits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:units,code',
            'symbol' => 'required|string|max:10',
            'base_unit_id' => 'nullable|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Unit::create($validated);

        return redirect()->route('units.index')->with('success', __('Unit of measurement created successfully.'));
    }

    public function edit(Unit $unit): View
    {
        $baseUnits = Unit::whereNull('base_unit_id')->where('id', '!=', $unit->id)->get();

        return view('units.edit', compact('unit', 'baseUnits'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:units,code,'.$unit->id,
            'symbol' => 'required|string|max:10',
            'base_unit_id' => 'nullable|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $unit->update($validated);

        return redirect()->route('units.index')->with('success', __('Unit updated successfully.'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->items()->exists() || $unit->derivedUnits()->exists()) {
            return back()->with('error', __('Cannot delete unit that is in use by items or derived units.'));
        }

        $unit->delete();

        return redirect()->route('units.index')->with('success', __('Unit deleted successfully.'));
    }
}
