<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        Gate::authorize('master_data.view');

        $customers = Customer::withCount('transactions')->latest()->paginate(15);

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        Gate::authorize('master_data.manage');

        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', __('Customer created successfully.'));
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('master_data.manage');

        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('master_data.manage');

        if ($customer->transactions()->exists()) {
            return back()->with('error', __('Cannot delete customer associated with transactions.'));
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer deleted successfully.'));
    }
}
