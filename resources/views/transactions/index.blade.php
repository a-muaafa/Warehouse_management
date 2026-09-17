@extends('layouts.app')

@section('title', __('Inbound & Outbound Movement Control'))
@section('page-title', __('Inbound & Outbound Movement Control'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Transactions') }}</span>
@endsection

@section('topbar-actions')
<button class="btn btn-primary" id="openTransactionModal">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    {{ __('Log Movement Transaction') }}
</button>
@endsection

@section('content')

{{-- Filter Bar --}}
<div class="filter-bar mb-4">
    <form method="GET" action="{{ route('transactions.index') }}" class="filter-form d-flex gap-2 flex-wrap">
        <select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Movement Directions') }}</option>
            <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>{{ __('Inbound Receipts (+)') }}</option>
            <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>{{ __('Outbound Issuances (-)') }}</option>
        </select>

        <select name="movement_reason" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Movement Reasons') }}</option>
            <option value="purchase" {{ request('movement_reason') === 'purchase' ? 'selected' : '' }}>{{ __('Purchase Receipt') }}</option>
            <option value="sale_delivery" {{ request('movement_reason') === 'sale_delivery' ? 'selected' : '' }}>{{ __('Sales Delivery / Order Issue') }}</option>
            <option value="customer_return" {{ request('movement_reason') === 'customer_return' ? 'selected' : '' }}>{{ __('Customer Return') }}</option>
            <option value="supplier_return" {{ request('movement_reason') === 'supplier_return' ? 'selected' : '' }}>{{ __('Supplier Return') }}</option>
            <option value="damage" {{ request('movement_reason') === 'damage' ? 'selected' : '' }}>{{ __('Damaged / Loss Write-Off') }}</option>
            <option value="transfer_in" {{ request('movement_reason') === 'transfer_in' ? 'selected' : '' }}>{{ __('Transfer In') }}</option>
            <option value="transfer_out" {{ request('movement_reason') === 'transfer_out' ? 'selected' : '' }}>{{ __('Transfer Out') }}</option>
            <option value="audit_adjustment" {{ request('movement_reason') === 'audit_adjustment' ? 'selected' : '' }}>{{ __('Audit Reconciliation') }}</option>
        </select>

        <select name="warehouse_id" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Warehouses') }}</option>
            @foreach($warehouses as $wh)
            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
            @endforeach
        </select>

        <input type="date" name="date" value="{{ request('date') }}" class="form-input" onchange="this.form.submit()">
        
        @if(request()->hasAny(['type', 'movement_reason', 'warehouse_id', 'date']))
        <a href="{{ route('transactions.index') }}" class="btn btn-secondary">{{ __('Reset Filters') }}</a>
        @endif
    </form>
</div>

@if($errors->any())
<div class="alert alert-error mb-4">
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

{{-- Transactions Table --}}
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Item & Code') }}</th>
                        <th>{{ __('Direction & Reason') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Unit Price & Total') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Doc Ref / Party') }}</th>
                        <th>{{ __('Operator') }}</th>
                        <th>{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                    <tr>
                        <td class="text-muted">{{ $tx->id }}</td>
                        <td class="font-medium">
                            <div style="font-weight:600;">{{ $tx->item->name }}</div>
                            <small class="text-muted">{{ $tx->item->barcode }}</small>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <span class="badge {{ $tx->type === 'in' ? 'badge-success' : 'badge-warning' }}" style="width:fit-content;">
                                    {{ $tx->type === 'in' ? __('IN') : __('OUT') }}
                                </span>
                                <small class="text-muted">{{ $tx->movement_reason_display_name }}</small>
                            </div>
                        </td>
                        <td class="font-medium">
                            <strong>{{ $tx->quantity }}</strong> {{ $tx->item->primaryUnit?->symbol ?: __($tx->item->unit ?? '') }}
                        </td>
                        <td>
                            <div>{{ number_format($tx->unit_price, 2) }} ر.س</div>
                            <small class="text-muted font-bold">{{ number_format($tx->total_amount, 2) }} ر.س</small>
                        </td>
                        <td>{{ $tx->warehouse?->name ?: __('Main Warehouse') }}</td>
                        <td>
                            @if($tx->reference_doc)
                            <code>{{ $tx->reference_doc }}</code>
                            @endif
                            @if($tx->supplier)
                            <div style="font-size:0.8rem; color:var(--color-primary);">{{ $tx->supplier->company_name }}</div>
                            @elseif($tx->customer)
                            <div style="font-size:0.8rem; color:#10b981;">{{ $tx->customer->name }}</div>
                            @endif
                            @if(!$tx->reference_doc && !$tx->supplier && !$tx->customer)
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $tx->user->name }}</td>
                        <td class="text-muted text-sm">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="empty-row">{{ __('No transactions matching criteria.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
        <div class="p-3">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>
</div>

{{-- New Transaction Modal --}}
<div class="modal-overlay" id="transactionModal">
    <div class="modal" style="max-width: 750px;">
        <div class="modal-header">
            <h3 class="modal-title">{{ __('Log Movement Transaction') }}</h3>
            <button class="modal-close" id="closeTransactionModal">×</button>
        </div>
        <form method="POST" action="{{ route('transactions.store') }}">
            @csrf
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Select Item') }} *</label>
                        <select name="item_id" class="form-select" required>
                            <option value="">{{ __('Select Item...') }}</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }} ({{ __('Stock:') }} {{ $item->quantity }} {{ $item->primaryUnit?->symbol ?: $item->unit }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Movement Direction') }} *</label>
                        <select name="type" class="form-select" id="modalTxType" required>
                            <option value="in" {{ old('type') === 'in' ? 'selected' : '' }}>{{ __('Stock In (Receipt)') }}</option>
                            <option value="out" {{ old('type') === 'out' ? 'selected' : '' }}>{{ __('Stock Out (Issue / Dispatch)') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Movement Reason') }} *</label>
                        <select name="movement_reason" class="form-select" required>
                            <option value="purchase">{{ __('Purchase from Vendor / Supplier') }}</option>
                            <option value="sale_delivery">{{ __('Sales Order Fulfillment / Client Dispatch') }}</option>
                            <option value="customer_return">{{ __('Customer Return') }}</option>
                            <option value="supplier_return">{{ __('Return to Supplier') }}</option>
                            <option value="damage">{{ __('Damaged / Expired Goods Write-off') }}</option>
                            <option value="audit_adjustment">{{ __('Stock Reconciliation Adjustment') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Warehouse Facility') }}</label>
                        <select name="warehouse_id" class="form-select">
                            <option value="">{{ __('Default / Main Warehouse') }}</option>
                            @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Quantity') }} *</label>
                        <input type="number" step="1" name="quantity" min="1" value="{{ old('quantity', 1) }}" class="form-input" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Unit of Measurement') }}</label>
                        <select name="unit_id" class="form-select">
                            <option value="">{{ __('Default Item Unit') }}</option>
                            @foreach($units as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->symbol }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Unit Price ($)') }}</label>
                        <input type="number" step="0.01" name="unit_price" value="{{ old('unit_price') }}" class="form-input" placeholder="{{ __('Optional') }}">
                    </div>

                    <!-- Associated Parties & Official Documents -->
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Supplier (for Inbound)') }}</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">{{ __('Select Supplier (Optional)') }}</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Customer (for Outbound)') }}</label>
                        <select name="customer_id" class="form-select">
                            <option value="">{{ __('Select Customer (Optional)') }}</option>
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Official Doc Ref # (PO / Invoice / DN)') }}</label>
                        <input type="text" name="reference_doc" class="form-input" placeholder="e.g. PO-89021 or INV-4412">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Batch / Lot Number (if new lot)') }}</label>
                        <input type="text" name="batch_number" class="form-input" placeholder="e.g. LOT-2026-X9">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ __('Notes / Inspection Comments') }}</label>
                        <textarea name="notes" class="form-textarea" rows="2" placeholder="{{ __('Optional remarks regarding goods condition...') }}">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" id="cancelTransaction">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save Movement') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const modal = document.getElementById('transactionModal');
document.getElementById('openTransactionModal').addEventListener('click', () => modal.classList.add('active'));
document.getElementById('closeTransactionModal').addEventListener('click', () => modal.classList.remove('active'));
document.getElementById('cancelTransaction').addEventListener('click', () => modal.classList.remove('active'));
modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
@if($errors->any()) modal.classList.add('active'); @endif
</script>
@endpush
