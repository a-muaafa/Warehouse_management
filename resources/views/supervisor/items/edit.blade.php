@extends('layouts.app')
@section('title', __('Edit Item'))
@section('page-title', __('Edit Item'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('supervisor.items.index') }}">{{ __('Items') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Inventory Item Details') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('supervisor.items.update', $item) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Item Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Category') }}</label>
                    <select name="category_id" class="form-select">
                        <option value="">{{ __('None / General') }}</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $item->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Barcode / QR Identifier') }} *</label>
                    <input type="text" name="barcode" class="form-control" value="{{ old('barcode', $item->barcode) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('SKU (Stock Keeping Unit)') }}</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku', $item->sku) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Primary Unit of Measurement') }}</label>
                    <select name="unit_id" class="form-select">
                        <option value="">{{ __('Default (Piece)') }}</option>
                        @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ old('unit_id', $item->unit_id) == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Current Quantity') }}</label>
                    <input type="number" class="form-control" value="{{ $item->quantity }}" disabled>
                    <small class="form-hint">{{ __('Adjust balances via Inbound/Outbound transactions or Audits.') }}</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Purchase Cost Price ($)') }}</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" value="{{ old('purchase_price', $item->purchase_price) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Selling / Outbound Price ($)') }}</label>
                    <input type="number" step="0.01" name="selling_price" class="form-control" value="{{ old('selling_price', $item->selling_price) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Inventory Valuation Method') }} *</label>
                    <select name="valuation_method" class="form-select" required>
                        <option value="fifo" {{ old('valuation_method', $item->valuation_method) === 'fifo' ? 'selected' : '' }}>{{ __('FIFO (First-In, First-Out)') }}</option>
                        <option value="lifo" {{ old('valuation_method', $item->valuation_method) === 'lifo' ? 'selected' : '' }}>{{ __('LIFO (Last-In, First-Out)') }}</option>
                        <option value="avg" {{ old('valuation_method', $item->valuation_method) === 'avg' ? 'selected' : '' }}>{{ __('Weighted Average Cost') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Minimum Alert Threshold') }} *</label>
                    <input type="number" name="min_alert_level" class="form-control" value="{{ old('min_alert_level', $item->min_alert_level) }}" required min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Reorder Point') }}</label>
                    <input type="number" name="reorder_point" class="form-control" value="{{ old('reorder_point', $item->reorder_point) }}" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Monthly Holding Cost ($/unit)') }}</label>
                    <input type="number" step="0.01" name="storage_cost_rate" class="form-control" value="{{ old('storage_cost_rate', $item->storage_cost_rate) }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Default Storage Bin / Aisle') }}</label>
                    <input type="text" name="default_location" class="form-control" value="{{ old('default_location', $item->default_location) }}">
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <label class="form-check mt-3">
                        <input type="checkbox" name="is_perishable" value="1" {{ old('is_perishable', $item->is_perishable) ? 'checked' : '' }}>
                        <span class="form-check-label">{{ __('Perishable Product (Requires FEFO Expiry Tracking)') }}</span>
                    </label>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Description & Notes') }}</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $item->description) }}</textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Item') }}</button>
                <a href="{{ route('supervisor.items.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
