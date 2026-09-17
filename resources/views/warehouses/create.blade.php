@extends('layouts.app')
@section('title', __('Create Warehouse'))
@section('page-title', __('Create Warehouse'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a> / <span>{{ __('Create') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('New Warehouse Facility Details') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('warehouses.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. Central Distribution Center">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Code / Identifier') }} *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code') }}" required placeholder="e.g. WH-NORTH-01">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Physical Address / Location') }}</label>
                    <input type="text" name="location_address" class="form-control" value="{{ old('location_address') }}" placeholder="Street, District, City">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact Person / Manager') }}</label>
                    <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Storage Capacity (m²)') }}</label>
                    <input type="number" step="0.1" name="capacity_sqm" class="form-control" value="{{ old('capacity_sqm', 500) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Daily Storage Overhead Rate ($/day)') }}</label>
                    <input type="number" step="0.01" name="holding_cost_rate_per_day" class="form-control" value="{{ old('holding_cost_rate_per_day', 15.00) }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Warehouse') }}</button>
                <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
