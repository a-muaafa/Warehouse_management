<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        Gate::authorize('master_data.view');

        $suppliers = Supplier::withCount('batches', 'transactions')->latest()->paginate(15);

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        Gate::authorize('master_data.manage');

        return view('suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', __('Supplier created successfully.'));
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('master_data.manage');

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', __('Supplier updated successfully.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        if ($supplier->batches()->exists() || $supplier->transactions()->exists()) {
            return back()->with('error', __('Cannot delete supplier associated with transactions or batches.'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier deleted successfully.'));
    }
}
