@extends('layouts.app')
@section('title', __('Add Item'))
@section('page-title', __('Add Item'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('supervisor.items.index') }}">{{ __('Items') }}</a> / <span>{{ __('Add') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('New Inventory Item Definition') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('supervisor.items.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Item Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. Industrial Hydraulic Filter">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('None / General') }}</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Barcode / QR Identifier') }}</label>
                    <input type="text" name="barcode" class="form-control" value="{{ old('barcode') }}" placeholder="{{ __('Auto-generated if left blank') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('SKU (Stock Keeping Unit)') }}</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" placeholder="e.g. FLT-HYD-001">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Primary Unit of Measurement') }}</label>
                    <select name="unit_id" class="form-select">
                        <option value="">{{ __('Default (Piece)') }}</option>
                        @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Initial Stock Balance') }} *</label>
                    <input type="number" name="quantity" class="form-control" value="{{ old('quantity', 0) }}" required min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Purchase Cost Price ($)') }}</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" value="{{ old('purchase_price', 0) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Selling / Outbound Price ($)') }}</label>
                    <input type="number" step="0.01" name="selling_price" class="form-control" value="{{ old('selling_price', 0) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Inventory Valuation Method') }} *</label>
                    <select name="valuation_method" class="form-select" required>
                        <option value="fifo" {{ old('valuation_method') === 'fifo' ? 'selected' : '' }}>{{ __('FIFO (First-In, First-Out)') }}</option>
                        <option value="lifo" {{ old('valuation_method') === 'lifo' ? 'selected' : '' }}>{{ __('LIFO (Last-In, First-Out)') }}</option>
                        <option value="avg" {{ old('valuation_method') === 'avg' ? 'selected' : '' }}>{{ __('Weighted Average Cost') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Minimum Alert Threshold') }} *</label>
                    <input type="number" name="min_alert_level" class="form-control" value="{{ old('min_alert_level', 5) }}" required min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Reorder Point') }}</label>
                    <input type="number" name="reorder_point" class="form-control" value="{{ old('reorder_point', 10) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Monthly Holding Cost ($/unit)') }}</label>
                    <input type="number" step="0.01" name="storage_cost_rate" class="form-control" value="{{ old('storage_cost_rate', 0.50) }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Default Storage Bin / Aisle') }}</label>
                    <input type="text" name="default_location" class="form-control" value="{{ old('default_location') }}" placeholder="e.g. Aisle 2, Bin 14">
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <label class="form-check mt-3">
                        <input type="checkbox" name="is_perishable" value="1" {{ old('is_perishable') ? 'checked' : '' }}>
                        <span class="form-check-label">{{ __('Perishable Product (Requires FEFO Expiry Tracking)') }}</span>
                    </label>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Description & Notes') }}</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Item') }}</button>
                <a href="{{ route('supervisor.items.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
