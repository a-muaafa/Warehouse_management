@extends('layouts.app')
@section('title', __('Register New Item Batch'))
@section('page-title', __('Register New Item Batch'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('batches.index') }}">{{ __('Batches') }}</a> / <span>{{ __('Register') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('New Inventory Lot / Batch Intake') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('batches.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Item / Product') }} *</label>
                    <select name="item_id" class="form-select" required>
                        <option value="">{{ __('Select Item...') }}</option>
                        @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->barcode }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Batch / Lot Number') }} *</label>
                    <input type="text" name="batch_number" class="form-control" value="{{ old('batch_number', 'LOT-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4))) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Destination') }}</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">{{ __('Default / Main Warehouse') }}</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Supplier / Vendor') }}</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">{{ __('None / Direct Purchase') }}</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Received Quantity') }} *</label>
                    <input type="number" step="0.01" name="quantity_received" class="form-control" value="{{ old('quantity_received', 100) }}" required min="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Purchase Unit Cost ($)') }} *</label>
                    <input type="number" step="0.01" name="purchase_cost" class="form-control" value="{{ old('purchase_cost', 10.00) }}" required min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Manufacture Date') }}</label>
                    <input type="date" name="manufacture_date" class="form-control" value="{{ old('manufacture_date') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Expiry Date') }}</label>
                    <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Storage Bin / Shelf Location') }}</label>
                    <input type="text" name="storage_location" class="form-control" placeholder="e.g. Aisle 4, Rack 2, Bin 10">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Register Batch') }}</button>
                <a href="{{ route('batches.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
