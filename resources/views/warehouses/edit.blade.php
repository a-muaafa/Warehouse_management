@extends('layouts.app')
@section('title', __('Edit Warehouse'))
@section('page-title', __('Edit Warehouse'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Warehouse Facility Details') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $warehouse->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Code / Identifier') }} *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $warehouse->code) }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Physical Address / Location') }}</label>
                    <input type="text" name="location_address" class="form-control" value="{{ old('location_address', $warehouse->location_address) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact Person / Manager') }}</label>
                    <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $warehouse->contact_person) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $warehouse->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Storage Capacity (m²)') }}</label>
                    <input type="number" step="0.1" name="capacity_sqm" class="form-control" value="{{ old('capacity_sqm', $warehouse->capacity_sqm) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Daily Storage Overhead Rate ($/day)') }}</label>
                    <input type="number" step="0.01" name="holding_cost_rate_per_day" class="form-control" value="{{ old('holding_cost_rate_per_day', $warehouse->holding_cost_rate_per_day) }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Warehouse') }}</button>
                <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
